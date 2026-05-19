<?php

class ElectricityMapsClient {


    public static function carbonIntensityLatest($zona) {
        return self::call('/carbon-intensity/latest?zone=' . urlencode($zona));
    }

    public static function powerBreakdownLatest($zona) {
        return self::call('/power-breakdown/latest?zone=' . urlencode($zona));
    }

    public static function listAvailableZones() {
        return self::call('/zones');
    }

    //chiama l'api di electricity maps e restituisce i dati decodificati

    private static function call($path) {
        $ch = curl_init(ELECTRICITY_MAPS_BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'progetto-finale/1.0',
            CURLOPT_HTTPHEADER => [
                'auth-token: ' . ELECTRICITY_MAPS_API_KEY,
                'Accept: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) return null;
        $data = json_decode((string) $resp, true);
        return is_array($data) ? $data : null;
    }
}
