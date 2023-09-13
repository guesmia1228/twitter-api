<?php

define('PLATFORM_NAME', 'TWITTER');
define('PLATFORM_ID', 3);
define('VERSIONCODIGO', 'FB_3.3.16'); //max 10 char
// define('VERSIONGTASKS', '_FB_3_3_16'); //max 10 char
define('VERSIONGTASKS', '_' . (getenv('VERSIONGTASKS') ? getenv('VERSIONGTASKS') :  VERSIONCODIGO));
define('VERBOSE', (getenv('VERBOSE') ? getenv('VERBOSE') :  TRUE));
define('FUNCTION_API_NAME', 'function-twitter-api');
define('CLOUD_COLANAME', (getenv('CLOUD_COLANAME') ? getenv('CLOUD_COLANAME') :  'twitter-sync'));
define('CLOUD_ENABLED', TRUE);
