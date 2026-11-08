<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_CAPI_Transport {
    /** The one Meta Graph API version used by every plugin request (CAPI, audiences, Lead Ads, Facebook login). Builder: Shashi. */
    const GRAPH_VERSION = 'v25.0';

    /** GA4 wire name; retain the original Google Ads label and receipt identity. */
    public static function google_event_name($label) {
        $name = str_replace(' ', '_', $label);
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/D', $name) ? $name : '';
    }

    public static function uses_whatsapp_attribution($lead, $attribution) {
        return empty($lead->fb_lead_id) && ($attribution['channel'] ?? '') === 'whatsapp';
    }

    /** Meta phone normalisation: digits only, with the country code. A 10-digit or 0-prefixed 11-digit number is Indian (+91). Builder: Shashi. */
    public static function meta_phone($phone) {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ('' === $digits) { return ''; }
        if (0 === strpos($digits, '00')) { $digits = substr($digits, 2); }
        elseif (11 === strlen($digits) && '0' === $digits[0]) { $digits = substr($digits, 1); }
        if (10 === strlen($digits)) { $digits = '91' . $digits; }
        return strlen($digits) >= 8 ? $digits : '';
    }

    /** Meta name normalisation: UTF-8 lowercase with punctuation and symbols removed. Builder: Shashi. */
    public static function meta_name($name) {
        $name = mb_strtolower(trim((string) $name), 'UTF-8');
        $name = preg_replace('/[\p{P}\p{S}]+/u', '', $name);
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }

    public static function meta_hash($value) {
        return '' === $value ? '' : hash('sha256', $value);
    }

    /** Meta user_data keys whose hashed values identify a person; logs keep only the key. */
    const LOGGED_HASH_KEYS = array('em', 'ph', 'fn', 'ln', 'ct', 'st', 'zp', 'country', 'external_id', 'db', 'ge');

    /** A phone or email hash is reversible by enumeration, so stored diagnostics show which keys were sent, not their values. */
    public static function mask_logged_identifiers($payload) {
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) { return $payload; }
        foreach ($payload['data'] as $index => $event) {
            if (!is_array($event) || !isset($event['user_data']) || !is_array($event['user_data'])) { continue; }
            foreach (array_keys($event['user_data']) as $key) {
                if (in_array($key, self::LOGGED_HASH_KEYS, true)) { $payload['data'][$index]['user_data'][$key] = '[HASHED]'; }
            }
        }
        return $payload;
    }

    public static function record_attempt($lead_id,$platform,$delivery,$payload,$response,$status,$attempted,$retryable,$outcome = null) {
        unset($delivery['credential']);
        $payload = self::mask_logged_identifiers($payload);
        $delivery['attempted']=$attempted;$delivery['retryable']=$retryable;$delivery['outcome']=$outcome ?? $status;
        $claimed = !empty($delivery['_durable_claim']);
        unset($delivery['_durable_claim']); $payload['_idlm_delivery']=$delivery;
        $save = static function() use ($lead_id, $platform, $delivery, $payload, $response, $status, $claimed) {
            $id = IDLM_DB::log_capi_event($lead_id, $platform, $delivery['event'], $payload, $response, $status);
            if ($claimed) {
                IDLM_Lead_Transaction::required($id > 0);
                IDLM_CAPI_Delivery::finish($delivery, $delivery['outcome']);
            }
            return $id;
        };
        $saved = $claimed ? IDLM_Lead_Transaction::run($save) : $save();
        $id = is_wp_error($saved) ? 0 : (int) $saved;
        $message = $id > 0 ? ('success' === $status ? ($platform === 'google' ? 'Google received the request; event processing is not confirmed.' : 'This attempt was accepted.') : ('uncertain' === $delivery['outcome'] ? 'Delivery is uncertain; reconciliation is required before replay.' : 'This attempt failed; inspect its delivery log.')) : 'Attempt outcome could not be recorded; reconciliation is required.';
        return array('success'=>$id > 0 && 'success' === $status,'message'=>$message,'attempted'=>$attempted,'log_id'=>$id,'attempt_id'=>$delivery['attempt_id'],'retryable'=>$retryable,'outcome'=>$id > 0 ? $delivery['outcome'] : 'uncertain');
    }

    public static function record_provider_response($lead_id, $platform, $delivery, $payload, $body, $code, $status) {
        if ('success' === $status) {
            return self::record_attempt($lead_id, $platform, $delivery, $payload, $body, $status, true, false);
        }
        // Google explicitly advises against replaying non-2xx Measurement Protocol requests.
        // Timeouts, server errors, redirects and unrecognized success bodies are ambiguous.
        $rejected = $platform !== 'google' && $code >= 400 && $code < 500 && $code !== 408;
        $retryable = $rejected && in_array($code, array(401, 403, 429), true);
        return self::record_attempt($lead_id, $platform, $delivery, $payload, $body, 'failed', true, $retryable, $rejected ? 'failed' : 'uncertain');
    }

    public static function fire_meta_capi_work($lead, $stage, $delivery) {
        $event_name = $delivery['event'];
        $pixel_id = $delivery['destination'];
        $access_token = $delivery['credential'];

        $ads_email = IDLM_Contact_Identity::email_for_ads($lead); $hashed_email = '' !== $ads_email ? hash('sha256', $ads_email) : '';
        $hashed_phone = self::meta_hash(self::meta_phone($lead->phone ?? ''));

        // CRM workers do not possess the visitor's browser IP or user agent.
        $user_data = array();
        
        // Hashed email and phone must be arrays of hashed strings according to Meta specification (implemented by Shashi)
        if (!empty($hashed_email)) {
            $user_data['em'] = array($hashed_email);
        }
        if (!empty($hashed_phone)) {
            $user_data['ph'] = array($hashed_phone);
        }


        $first_hash = self::meta_hash(self::meta_name($lead->first_name ?? ''));
        if ('' !== $first_hash) { $user_data['fn'] = $first_hash; }
        $last_hash = self::meta_hash(self::meta_name($lead->last_name ?? ''));
        if ('' !== $last_hash) { $user_data['ln'] = $last_hash; }

        // A bare fbclid does not establish the original _fbc cookie's timestamp.
        // Do not fabricate it from the CRM lead creation time (or send seconds in
        // place of the required millisecond cookie timestamp).

        // Preserve the exact Meta identifier without floating-point conversion.
        if (!empty($lead->fb_lead_id)) {
            $user_data['lead_id'] = (string) $lead->fb_lead_id;
        }

        $custom_data = array(
            'event_source' => 'crm',
            'lead_event_source' => 'Interior Design Lead Manager',
            'lead_id' => $lead->id,
            'stage' => $stage,
            'property_type' => $lead->property_type,
            'num_rooms' => $lead->num_rooms,
            'source' => $lead->source
        );

        if ($delivery['value'] > 0) {
            $custom_data['value'] = $delivery['value'];
            $custom_data['currency'] = $delivery['currency'];
        }

        $payload = array(
            'data' => array(
                array(
                    'event_name' => $event_name,
                    'event_time' => $delivery['occurred_at'],
                    'event_source_url' => home_url('/'),
                    'action_source' => 'system_generated',
                    'event_id' => $delivery['event_id'],
                    'user_data' => array_filter($user_data),
                    'custom_data' => $custom_data
                )
            )
        );

        $attribution=empty($lead->fb_lead_id) ? IDLM_Lead_Connector::attribution($lead->id) : null;
        if (is_wp_error($attribution)) { throw new RuntimeException('Lead attribution is unreadable.'); }
        // A CRM stage event has no browser event URL.
        unset($payload['data'][0]['event_source_url']);
        if (self::uses_whatsapp_attribution($lead, $attribution)) {
            $payload['data'][0]['action_source']='business_messaging';
            $payload['data'][0]['messaging_channel']='whatsapp';
            $payload['data'][0]['user_data']=array('ctwa_clid'=>$attribution['ctwa_clid'],'page_id'=>$attribution['page_id']);
        }

        $endpoint = "https://graph.facebook.com/" . self::GRAPH_VERSION . "/" . rawurlencode($pixel_id) . "/events";
        
        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        $response = IDLM_Secret_Store::request($endpoint, array(
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
            'body'    => json_encode($payload),
            'timeout' => 15
        ), array($access_token));

        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            return self::record_attempt($lead->id, 'meta', $delivery, $payload, $err_msg, 'failed', true, false, 'uncertain');
        } else {
            $body = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $status = 'failed';
            if ($code == 200 && !empty($body)) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['events_received']) && intval($decoded['events_received']) > 0 && !isset($decoded['error'])) {
                    $status = 'success';
                }
            }
            return self::record_provider_response($lead->id, 'meta', $delivery, $payload, $body, $code, $status);
        }
    }

    public static function fire_google_capi_work($lead, $stage, $delivery) {
        $event_name = self::google_event_name($delivery['event']);
        $measurement_id = $delivery['destination'];
        $api_secret = $delivery['credential'];

        // GA4 requires a numeric pair, not a click ID or an "idlm." prefix.
        // This stable CRM identity does not claim browser-session association;
        // authentic click IDs remain attribution parameters below.
        $client_id = sprintf('%u', crc32(home_url())) . '.' . (int) $lead->id;

        $event_params = array(
            'engagement_time_msec' => 100,
            'lead_id' => $lead->id,
            'source' => $lead->source,
            'gclid' => $lead->gclid,
            'gbraid' => isset($lead->gbraid) ? $lead->gbraid : '',
            'wbraid' => isset($lead->wbraid) ? $lead->wbraid : '',
            'stage' => $stage,
            'property_type' => $lead->property_type,
            'num_rooms' => $lead->num_rooms
        );

        if ($delivery['value'] > 0) {
            $event_params['value'] = $delivery['value'];
            $event_params['currency'] = $delivery['currency'];
        }

        $payload = array(
            'client_id' => $client_id,
            'events' => array(
                array(
                    'name' => $event_name,
                    'params' => $event_params
                )
            )
        );

        $endpoint = "https://www.google-analytics.com/mp/collect?measurement_id=" . rawurlencode($measurement_id) . "&api_secret=" . rawurlencode($api_secret);

        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        $response = IDLM_Secret_Store::request($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode($payload),
            'timeout' => 15
        ), array($api_secret));

        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            return self::record_attempt($lead->id, 'google', $delivery, $payload, $err_msg, 'failed', true, false, 'uncertain');
        } else {
            $body = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            // GA4 Measurement Protocol returns 204 No Content on success
            $status = ($code >= 200 && $code < 300) ? 'success' : 'failed';
            
            $log_resp = !empty($body) ? $body : "Success Response (Code {$code})";
            return self::record_provider_response($lead->id, 'google', $delivery, $payload, $log_resp, $code, $status);
        }
    }

    public static function fire_linkedin_capi_work($lead, $stage, $delivery) {
        $event_name = $delivery['event'];
        $conversion_id = $delivery['destination'];
        $access_token = $delivery['credential'];

        $ads_email = IDLM_Contact_Identity::email_for_ads($lead); $hashed_email = '' !== $ads_email ? hash('sha256', $ads_email) : '';

        $user_ids = array();
        if (!empty($lead->li_fat_id)) {
            $user_ids[] = array(
                'idType' => 'LI_FAT_ID',
                'idValue' => $lead->li_fat_id
            );
        }

        $payload = array(
            'conversion' => "urn:lla:llaPartnerConversion:{$conversion_id}",
            'conversionHappenedAt' => $delivery['occurred_at'] * 1000, // Miliseconds
            'user' => array(
                'userIds' => $user_ids,
                'emailHashes' => array_filter(array($hashed_email))
            )
        );

        if ($delivery['value'] > 0) {
            $payload['conversionValue'] = array(
                'amount' => number_format($delivery['value'], 2, '.', ''),
                'currencyCode' => $delivery['currency']
            );
        }

        $endpoint = "https://api.linkedin.com/v2/conversionEvents";

        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        $response = IDLM_Secret_Store::request($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
            ),
            'body'    => json_encode($payload),
            'timeout' => 15
        ), array($access_token));

        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            return self::record_attempt($lead->id, 'linkedin', $delivery, $payload, $err_msg, 'failed', true, false, 'uncertain');
        } else {
            $body = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $status = 'failed';
            if ($code == 201 && empty($body)) {
                $status = 'success';
            } elseif (($code == 201 || $code == 200) && !empty($body)) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && !isset($decoded['error']) && !isset($decoded['serviceErrorCode'])) {
                    $status = 'success';
                }
            }
            return self::record_provider_response($lead->id, 'linkedin', $delivery, $payload, $body, $code, $status);
        }
    }

}
