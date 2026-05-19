<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/Router.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ElectricityMapsClient.php';
require_once __DIR__ . '/../lib/NewsApiClient.php';

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/zone_salvate.php';
require_once __DIR__ . '/commenti.php';
require_once __DIR__ . '/categorie.php';
require_once __DIR__ . '/confronti.php';
require_once __DIR__ . '/zone.php';
require_once __DIR__ . '/news.php';
require_once __DIR__ . '/statistiche.php';
require_once __DIR__ . '/admin.php';

session_start();

Router::post ('/api/auth/register', 'register');
Router::post ('/api/auth/login', 'login_user');
Router::post ('/api/auth/logout', 'logout_user');
Router::get ('/api/auth/me', 'me');


Router::get ('/api/users', 'list_users');
Router::get ('/api/users/me', 'get_me');
Router::put ('/api/users/me', 'upd_me');
Router::patch ('/api/users/me/password', 'cambia_pw');
Router::delete('/api/users/:id', 'del_user');


Router::get ('/api/zone-salvate', 'pref_list');
Router::post ('/api/zone-salvate', 'pref_save');
Router::put ('/api/zone-salvate/:zona_id', 'pref_put');
Router::patch ('/api/zone-salvate/:zona_id', 'pref_patch');
Router::delete('/api/zone-salvate/:zona_id', 'pref_del');


Router::get ('/api/zone/:id/commenti', 'comm_zona');
Router::post ('/api/commenti', 'comm_new');
Router::patch ('/api/commenti/:id', 'comm_edit');
Router::delete('/api/commenti/:id', 'comm_del');


Router::get ('/api/categorie', 'cat_list');
Router::get ('/api/zone/:id/categorie', 'cat_zona');
Router::post ('/api/categorie/ricalcola', 'cat_recalc');


Router::get ('/api/confronti', 'conf_list');
Router::post ('/api/confronti', 'conf_new');
Router::get ('/api/confronti/:id', 'conf_get');
Router::delete('/api/confronti/:id', 'conf_del');


Router::get ('/api/zone', 'zone_list');
Router::get ('/api/zone/:id', 'zona_get');
Router::get ('/api/zone/:id/lettura-live', 'lettura_live');
Router::get ('/api/zone/:id/letture-storiche', 'storico');


Router::get ('/api/zone/:id/news', 'news_zona');


Router::get ('/api/statistiche/top-co2', 'top_co2');
Router::get ('/api/statistiche/zone-categorizzate', 'stats_cat');
Router::get ('/api/statistiche/riepilogo', 'riepilogo');



Router::get ('/api/admin/stats', 'admin_stats');
Router::get ('/api/admin/news', 'admin_news');
Router::delete('/api/admin/news/:id', 'admin_del_news');
Router::delete('/api/admin/zone/:id', 'admin_del_zone');

Router::dispatch();
