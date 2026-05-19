<?php

class NewsApiClient {


    public static function topHeadlines($iso, $cat = 'business') {
        return self::call('/top-headlines?' . http_build_query([
            'country' => strtolower($iso),
            'category' => $cat,
            'pageSize' => 20,
        ]));
    }


    
    public static function everything($q, $lang = 'it') {
        return self::call('/everything?' . http_build_query([
            'q' => $q,
            'language' => $lang,
            'sortBy' => 'publishedAt',
            'pageSize' => 20,
        ]));
    }

    private static function call($path) {
        $ch = curl_init(NEWSAPI_BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            // NewsAPI rifiuta con 400 senza User-Agent
            CURLOPT_USERAGENT => 'progetto-finale/1.0',
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . NEWSAPI_KEY,
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
