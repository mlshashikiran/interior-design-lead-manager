<?php
/** Restricted MCP SELECT compiler. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_MCP_Query_Policy {
    private $tokens = array();
    private $position = 0;
    private $tables = array();
    private $references = array();
    private $output_aliases = array();
    private $allowed_tables;
    private $input;

    /** Parse the entire input; never execute or forward unconsumed SQL. */
    public static function prepare($sql, $allowed_tables) {
        if (!is_string($sql) || '' === trim($sql)) { return array('error' => 'Query rejected: A SELECT query is required.'); }
        if (strlen($sql) > IDLM_Production_Security::mcp_query_length_limit()) {
            return array('error' => 'Query rejected: Query length exceeds the configured character limit.');
        }
        try {
            $parser = new self();
            $parser->allowed_tables = array_map('strtolower', (array) $allowed_tables);
            $parser->tokenize($sql);
            return array('query' => $parser->select());
        } catch (InvalidArgumentException $e) {
            // Messages contain policy descriptions, never caller literals/SQL.
            return array('error' => 'Query rejected: ' . $e->getMessage());
        }
    }

    /** Token classes are explicit; strings support doubled quotes, not backslashes. */
    private function tokenize($sql) {
        $this->input = $sql;
        $pattern = "~\\G(?:([ \\t\\r\\n]+)|(`?[A-Za-z_][A-Za-z0-9_]*`?)|([0-9]+(?:\\.[0-9]+)?)|('(?:[^'\\\\\\x00-\\x1f]|'')*')|(<>|!=|<=|>=|[=<>(),.*+-]))~";
        $offset = 0;
        while ($offset < strlen($sql)) {
            $start = $offset;
            if (!preg_match($pattern, $sql, $match, 0, $offset)) {
                $this->reject('Unsupported syntax; comments, null bytes, variables and multiple statements are not permitted.');
            }
            $offset += strlen($match[0]);
            if (!empty($match[1])) { continue; }
            if (!empty($match[2])) {
                $value = $match[2];
                if (($value[0] === '`') !== (substr($value, -1) === '`')) { $this->reject('Unsupported quoted identifier.'); }
                $this->tokens[] = array('identifier', trim($value, '`'), $value[0] === '`');
            } elseif (isset($match[3]) && '' !== $match[3]) { $this->tokens[] = array('number', $match[3]); }
            elseif (isset($match[4]) && '' !== $match[4]) { $this->tokens[] = array('string', str_replace("''", "'", substr($match[4], 1, -1))); }
            else { $this->tokens[] = array('symbol', $match[5]); }
            $index = count($this->tokens) - 1;
            $this->tokens[$index][3] = $start;
            $this->tokens[$index][4] = $offset;
        }
    }

    private function select() {
        global $wpdb;
        if (!$this->take('SELECT')) { $this->reject('Only read-only SELECT queries are permitted.'); }
        $distinct = $this->take('DISTINCT') ? 'DISTINCT ' : '';
        $fields = array();
        do {
            $start = $this->tokens[$this->position][3] ?? 0;
            $computed = isset($this->tokens[$this->position + 1]) && '(' === $this->tokens[$this->position + 1][1];
            $field = $this->expression();
            $end = $this->tokens[$this->position - 1][4];
            if ($this->take('AS')) {
                $alias = $this->identifier();
                if (isset($this->output_aliases[strtolower($alias)])) { $this->reject('Duplicate output aliases are not permitted.'); }
                $this->output_aliases[strtolower($alias)] = true;
                $field .= ' AS ' . $this->quote($alias);
            } elseif ($computed) {
                // Preserve the caller's computed-column label after SQL quoting.
                $field .= ' AS ' . $this->quote(substr($this->input, $start, $end - $start));
            }
            $fields[] = $field;
        } while ($this->take(','));
        if (!$this->take('FROM')) { $this->reject('An explicit allowlisted FROM table is required.'); }
        $from = $this->table();
        $joins = '';
        while ($this->at('JOIN') || $this->at('INNER') || $this->at('LEFT')) {
            $type = 'INNER';
            if ($this->take('LEFT')) { $type = 'LEFT'; $this->take('OUTER'); }
            else { $this->take('INNER'); }
            $this->expect('JOIN');
            $existing = array_keys($this->tables);
            $target = $this->table();
            $new_alias = array_keys($this->tables); $new_alias = end($new_alias);
            $this->expect('ON');
            $left = $this->column(); $this->expect('='); $right = $this->column();
            $left_ref = $this->references[count($this->references) - 2];
            $right_ref = $this->references[count($this->references) - 1];
            if (!((strtolower($left_ref[0]) === $new_alias && in_array(strtolower($right_ref[0]), $existing, true)) ||
                (strtolower($right_ref[0]) === $new_alias && in_array(strtolower($left_ref[0]), $existing, true)))) {
                $this->reject('JOIN requires qualified columns linking the new table to an existing table.');
            }
            $joins .= ' ' . $type . ' JOIN ' . $target . ' ON ' . $left . ' = ' . $right;
        }
        $tail = '';
        if ($this->take('WHERE')) { $tail .= ' WHERE ' . $this->condition(); }
        if ($this->take('GROUP')) { $this->expect('BY'); $tail .= ' GROUP BY ' . $this->column_list(false); }
        if ($this->take('ORDER')) { $this->expect('BY'); $tail .= ' ORDER BY ' . $this->column_list(true); }
        $rows = IDLM_Production_Security::mcp_query_row_limit(); $offset = 0;
        if ($this->take('LIMIT')) {
            $first = $this->integer();
            if ($this->take(',')) { $offset = $first; $rows = min($rows, $this->integer()); }
            else { $rows = min($rows, $first); if ($this->take('OFFSET')) { $offset = $this->integer(); } }
        }
        if ($offset > 100000) { $this->reject('Pagination offset exceeds the 100000 row budget.'); }
        if ($this->position !== count($this->tokens)) { $this->reject('Unsupported trailing syntax or table expression.'); }
        $this->validate_references();
        // MySQL enforces the statement time budget, including aggregate work.
        // This internally generated hint cannot be supplied by the caller.
        $milliseconds = (int) ceil(IDLM_Production_Security::mcp_request_time_limit() * 1000);
        return 'SELECT /*+ MAX_EXECUTION_TIME(' . $milliseconds . ') */ ' . $distinct . implode(', ', $fields) .
            ' FROM ' . $from . $joins . $tail . ' LIMIT ' . $rows . ($offset ? ' OFFSET ' . $offset : '');
    }

    private function table() {
        global $wpdb;
        $name = $this->identifier();
        if ($this->at('.')) { $this->reject('Database-qualified table references are not permitted.'); }
        $key = strtolower($name);
        $schema = self::fields();
        if (!in_array($key, $this->allowed_tables, true) || !isset($schema[$key])) { $this->reject('Table access is restricted.'); }
        $alias = $name;
        if ($this->take('AS')) { $alias = $this->identifier(); }
        elseif ($this->peek_type('identifier') && !$this->is_keyword()) { $alias = $this->identifier(); }
        $alias_key = strtolower($alias);
        if (isset($this->tables[$alias_key]) || count($this->tables) >= 4) { $this->reject('At most four distinct table aliases are permitted.'); }
        $this->tables[$alias_key] = $schema[$key];
        $source = $this->quote($name);
        if ($key === strtolower($wpdb->users)) {
            // Caller-visible wp_users contains only CRM-associated ID/name.
            // Neither authentication columns nor unrelated accounts enter it.
            $leads = $this->quote($wpdb->prefix . 'idlm_leads');
            $source = '(SELECT ID, display_name FROM ' . $this->quote($wpdb->users) . ' WHERE ID IN (SELECT assigned_rep_id FROM ' . $leads .
                ' UNION SELECT intake_owner_id FROM ' . $leads . ' UNION SELECT qualified_by_user_id FROM ' . $leads . '))';
        }
        return $source . ' AS ' . $this->quote($alias);
    }

    /** Explicit schema projections; new columns never become grants implicitly. */
    public static function fields() {
        global $wpdb;
        $leads = array('id','deal_value','discount_percentage','property_type','num_rooms','source','original_source','latest_source','stage','call_status','lead_quality',
            'floor_plan_received','quote_shared','quote_value','disqualification_method','disqualified_at','disqualified_by_user_id','assigned_rep_id','assigned_at',
            'conversion_time','qualified_at','qualified_by_user_id','intake_owner_id','handoff_status','handoff_at','handoff_method','is_duplicate','duplicate_of_id',
            'created_at','updated_at','time_to_connected','time_to_won');
        if (IDLM_Production_Security::mcp_pii_allowed()) {
            $leads = array_merge($leads, array('first_name','last_name','email','phone','project_name','budget','project_timeline','possession_date','property_location','preferred_studio','callback_datetime'));
        }
        return array(
            strtolower($wpdb->prefix . 'idlm_leads') => $leads,
            strtolower($wpdb->prefix . 'idlm_lead_activity_log') => array('id','lead_id','user_id','activity_type','created_at'),
            strtolower($wpdb->prefix . 'idlm_capi_logs') => array('id','lead_id','platform','event_name','status','created_at'),
            strtolower($wpdb->prefix . 'idlm_audience_sync_logs') => array('id','platform','audience_id','lead_count','status','created_at'),
            strtolower($wpdb->prefix . 'idlm_telemetry_logs') => array('id','log_type','severity','created_at'),
            strtolower($wpdb->users) => array('ID','display_name'),
        );
    }

    private function expression($depth = 0) {
        if ($depth > 8) { $this->reject('Expression nesting exceeds the budget.'); }
        if ($this->peek_type('identifier') && isset($this->tokens[$this->position + 1]) && '(' === $this->tokens[$this->position + 1][1]) {
            $function = strtoupper($this->identifier());
            if (!in_array($function, array('COUNT','SUM','AVG','MIN','MAX','DATE','YEAR','MONTH'), true)) { $this->reject('Unsupported or unsafe database functions are not permitted.'); }
            $this->expect('(');
            $distinct = $this->take('DISTINCT') ? 'DISTINCT ' : '';
            if ('COUNT' === $function && !$distinct && $this->take('*')) { $argument = '*'; }
            else { $argument = $this->expression($depth + 1); }
            $this->expect(')');
            return $function . '(' . $distinct . $argument . ')';
        }
        return $this->column();
    }

    private function column($allow_output_alias = false) {
        $first = $this->identifier(); $alias = ''; $name = $first;
        if ($this->take('.')) { $alias = $first; $name = $this->identifier(); }
        if ($allow_output_alias && !$alias && isset($this->output_aliases[strtolower($name)])) { return $this->quote($name); }
        $this->references[] = array($alias, $name);
        return ($alias ? $this->quote($alias) . '.' : '') . $this->quote($name);
    }

    private function validate_references() {
        foreach ($this->references as $reference) {
            list($alias, $name) = $reference;
            if ($alias) {
                if (!isset($this->tables[strtolower($alias)]) || !in_array(strtolower($name), array_map('strtolower', $this->tables[strtolower($alias)]), true)) {
                    $this->reject('Column access is restricted by table and PII grants.');
                }
            } else {
                $matches = 0;
                foreach ($this->tables as $fields) { if (in_array(strtolower($name), array_map('strtolower', $fields), true)) { $matches++; } }
                if (1 !== $matches) { $this->reject('Column access is restricted or ambiguous; qualify approved fields.'); }
            }
        }
    }

    private function column_list($order) {
        $items = array();
        do {
            $item = $this->column(true);
            if ($order) { if ($this->take('DESC')) { $item .= ' DESC'; } elseif ($this->take('ASC')) { $item .= ' ASC'; } }
            $items[] = $item;
        } while ($this->take(','));
        return implode(', ', $items);
    }

    private function condition($depth = 0) {
        if ($depth > 8) { $this->reject('Condition nesting exceeds the budget.'); }
        $parts = array();
        do {
            if ($parts) { $parts[] = strtoupper($this->tokens[$this->position++][1]); }
            if ($this->take('(')) { $parts[] = '(' . $this->condition($depth + 1) . ')'; $this->expect(')'); }
            else {
                $column = $this->column();
                if ($this->take('IS')) { $not = $this->take('NOT') ? 'NOT ' : ''; $this->expect('NULL'); $parts[] = $column . ' IS ' . $not . 'NULL'; }
                elseif ($this->take('BETWEEN')) { $lower = $this->literal(); $this->expect('AND'); $parts[] = $column . ' BETWEEN ' . $lower . ' AND ' . $this->literal(); }
                else {
                    $not = $this->take('NOT') ? 'NOT ' : '';
                    if ($this->take('IN')) {
                        $this->expect('('); $values = array();
                        do { $values[] = $this->literal(); } while ($this->take(','));
                        $this->expect(')'); $parts[] = $column . ' ' . $not . 'IN (' . implode(', ', $values) . ')';
                    } elseif ($this->take('LIKE')) { $parts[] = $column . ' ' . $not . 'LIKE ' . $this->literal(); }
                    else {
                        if ($not) { $this->reject('Unsupported condition.'); }
                        $operator = $this->tokens[$this->position++][1] ?? '';
                        if (!in_array($operator, array('=','<>','!=','<','>','<=','>='), true)) { $this->reject('Unsupported comparison.'); }
                        $parts[] = $column . ' ' . $operator . ' ' . $this->literal();
                    }
                }
            }
        } while ($this->at('AND') || $this->at('OR'));
        return implode(' ', $parts);
    }

    private function literal() {
        global $wpdb;
        $sign = $this->take('-') ? '-' : '';
        if ($this->peek_type('number')) { return $sign . $this->tokens[$this->position++][1]; }
        if (!$sign && $this->peek_type('string')) { return $wpdb->prepare('%s', $this->tokens[$this->position++][1]); }
        $this->reject('Conditions require numeric or single-quoted literal values.');
    }

    private function integer() {
        if (!$this->peek_type('number')) { $this->reject('LIMIT must be a trailing numeric clause.'); }
        $value = $this->tokens[$this->position++][1];
        if (!preg_match('/^[0-9]{1,9}$/D', $value)) { $this->reject('LIMIT must be a bounded integer.'); }
        return (int) $value;
    }

    private function identifier() {
        if (!$this->peek_type('identifier')) { $this->reject('Explicit field and table identifiers are required; SELECT star is not permitted.'); }
        return $this->tokens[$this->position++][1];
    }
    private function peek_type($type) { return isset($this->tokens[$this->position]) && $type === $this->tokens[$this->position][0]; }
    private function at($value) {
        $token = $this->tokens[$this->position] ?? null;
        return $token && empty($token[2]) && 'string' !== $token[0] && 0 === strcasecmp($token[1], $value);
    }
    private function take($value) { if (!$this->at($value)) { return false; } $this->position++; return true; }
    private function expect($value) { if (!$this->take($value)) { $this->reject('Unsupported SELECT grammar.'); } }
    private function is_keyword() {
        foreach (array('JOIN','INNER','LEFT','RIGHT','CROSS','NATURAL','ON','WHERE','GROUP','ORDER','HAVING','LIMIT','UNION','FOR','LOCK','INTO','USE','FORCE','OFFSET') as $keyword) { if ($this->at($keyword)) { return true; } }
        return false;
    }
    private function quote($identifier) { return '`' . str_replace('`', '``', $identifier) . '`'; }
    private function reject($message) {
        // Parser callers encode this exception as JSON; this is not an HTML sink.
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Preserve parser text for JSON transport.
        throw new InvalidArgumentException($message);
    }
}
