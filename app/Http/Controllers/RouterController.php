<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Hborras\TwitterAdsSDK\TwitterAds;
use Hborras\TwitterAdsSDK\TwitterAds\Account;
use Hborras\TwitterAdsSDK\TwitterAds\Campaign\LineItem;
use Hborras\TwitterAdsSDK\TwitterAds\Campaign\TargetingCriteria;
use Hborras\TwitterAdsSDK\TwitterAds\Campaign\Campaign;
use Hborras\TwitterAdsSDK\TwitterAds\Campaign\FundingInstrument;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\BadRequest;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\Forbidden;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\NotAuthorized;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\NotFound;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\RateLimit;
use Hborras\TwitterAdsSDK\TwitterAds\Creative\PromotedTweet;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\ServerError;
use Hborras\TwitterAdsSDK\TwitterAds\Errors\ServiceUnavailable;
use Hborras\TwitterAdsSDK\TwitterAdsException;
use Hborras\TwitterAdsSDK\TwitterAds\Cursor;
use Hborras\TwitterAdsSDK\TwitterAds\Enumerations;
use Hborras\TwitterAdsSDK\TwitterAds\Fields\AnalyticsFields;
use Abraham\TwitterOAuth\TwitterOAuth;
use Hborras\TwitterAdsSDK\TwitterAds\Fields\LineItemFields;
use Exception;

class RouterController extends Controller
{
    const MAPA_DEVICES = ['Android devices' => 'MOBILE', 'Desktop and laptop computers' => 'DESKTOP', 'iOS devices' => 'MOBILE'];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        Cursor::setDefaultUseImplicitFetch(true);
    }

    public function loginApi($infoCredentials, $appId, $appSecret)
    {
        $user_credentials = json_decode($infoCredentials['retorno']);
        $twitterapi = TwitterAds::init($infoCredentials['app_id'], $infoCredentials['app_secret'], $user_credentials->oauth_token, $user_credentials->oauth_token_secret);
        return    $twitterapi;
    }

    public function loginApiBasic($infoCredentials)
    {
        $user_credentials = json_decode($infoCredentials['retorno']);
        $basicapi = new TwitterOAuth($infoCredentials['app_id'], $infoCredentials['app_secret'], $user_credentials->oauth_token, $user_credentials->oauth_token_secret);
        $content = $basicapi->get("account/verify_credentials");
        //print_r($content );
        //	print_r('fin loginApiBasic '.PHP_EOL);
        return $basicapi;
    }


    public function get_account_ads($adAccount_data, $infoCredentials, $twitterapi = FALSE, $apibasic = FALSE)
    {
        //$promotable_users = $twitterapi->get('accounts/'.$adAccountPlatformId.'/promotable_users', array('with_deleted'=> true));

        if (!isset($adAccount_data['account_platform_id'])) {
            return false;
        }
        $adAccountPlatformId = $adAccount_data['account_platform_id'];

        if (!$twitterapi) {
            $twitterapi = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
        }
        if (!$apibasic) {
            $apibasic = $this->loginApiBasic($infoCredentials);
        }


        try {
            if (VERBOSE) {
                echo 'Ad Account = ' . $adAccount_data['account_platform_id'] . PHP_EOL . PHP_EOL;
            }
            $account = new Account($adAccount_data['account_platform_id']);


            $account->read();

            // usar total count reduce el numero de request a 200 en 15 minutos TwitterAds\Fields\CampaignFields::WITH_TOTAL_COUNT => true,TwitterAds\Fields\CampaignFields::WITH_DRAFT=> false,
            //  $items = $twitterapi->get('accounts/'.$adAccountPlatformId.'/promoted_tweets', array('with_total_count'=>true,'count'=>4, 'with_deleted'=> true));
            Cursor::setDefaultUseImplicitFetch(true);
            /*
        $promotedTweet = new PromotedTweet();
        print_r(get_class_methods($promotedTweet));
    
        $items= $promotedTweet->loadResource(null, array('with_total_count'=>true,'count'=>4, 'with_deleted'=> true) );
        print_r( $items->getDefaultUseImplicitFetch());
        print_r(get_class_methods($items));
        //  $itemsb= $promotedTweet->fromResponse($items->getBody()->data);
    
        //$cuentaTwBBDD = getAdAccount_Element($data['ad_account_id']);
        print_r($items->fromResponse($items) );
        **/
            $params = [TwitterAds\Fields\CampaignFields::COUNT => 4, TwitterAds\Fields\CampaignFields::WITH_DELETED => true, TwitterAds\Fields\CampaignFields::WITH_DRAFT => false, TwitterAds\Fields\CampaignFields::SORT_BY => 'created_at-desc'];
            $items = $account->getPromotedTweets('', $params);

            //  $account->setUseImplicitFetch(true);
            $items->setUseImplicitFetch(true);
            while (!$items->isExhausted()) {
                $items->fetchNext();
            }
            $itemsdata = $items->getCollection();
            $promotedTweets = [];
            // $itemDatabulk=[];
            foreach ($itemsdata as $item) {
                // print_r($item);

                //        print_r(get_class_methods($item));
                if (VERBOSE) {
                    echo 'ID add = ' . $item->getId() . ' - Nombre = ' . $item->getTweetId() . PHP_EOL;
                }
                $itemData = [];
                $itemData['id'] =  $item->getId();
                $itemData['tweetid'] =   $item->getTweetId();
                $itemData['campaign_id'] = null;
                $itemData['preview_shareable_link'] = null;
                $itemData['adset_id'] =   $item->getLineItemId();
                $itemData['name'] =   $item->getTweetId();


                $itemData['status'] =  $item->getEntityStatus();
                $itemData['effective_status'] =  $item->getApprovalStatus();

                $itemData['promoted_object'] = [$item->getTweetId()];
                /*        $itemData['start_time']=  ($item->getStartTime())?  $item->getStartTime()->getTimestamp():null  ;
          $itemData['end_time']=   ($item->getEndTime())?  $item->getEndTime()->getTimestamp():null  ;
          $itemData['lifetime_budget']=   $item->getTotalBudgetAmountLocalMicro()/1000000 ;
          $itemData['puja_valor']=   $item->getBidAmountLocalMicro()/1000000 ;
          $itemData['bid_strategy']=   $item->getBidStrategy() ;
          $itemData['product_type']=   $item->getProductType() ;
          $itemData['daily_budget']=   null  ;
          $itemData['targeting']['publisher_platforms']=   $item->getPlacements() ;*/
                $itemData['metadata']['promoted'] =  $item->toArray();
                $promotedTweets[$item->getTweetId()] = $itemData;
            }
            $i = 0;
            $totalitems = count($promotedTweets);
            $tweets = [];
            //  echo count($promotedTweets).PHP_EOL;
            $tweetParams = ['timeline_type' => 'ALL', 'trim_user' => true, 'tweet_type' => 'PUBLISHED', 'tweet_ids' => ''];
            foreach ($promotedTweets as $tweet) {
                $i++;
                $tweets[] = $tweet['tweetid'];
                if ($i == 200 || $i == $totalitems) {
                    $tweetParams['tweet_ids'] = implode(',', $tweets);

                    $adsTweets = $twitterapi->get('accounts/' . $adAccount_data['account_platform_id'] . '/tweets', $tweetParams);
                    //  print_r($adsTweets->getBody()->data[0]);
                    foreach ($adsTweets->getBody()->data   as $creative) {
                        $url = isset($creative->entities->urls[0]) ? $creative->entities->urls[0]->expanded_url  : '';
                        $mediaurl = isset($creative->entities->media[0]) ? $creative->entities->media[0]->media_url_https  : '';
                        $mediatype = isset($creative->entities->media[0]) ? $creative->entities->media[0]->type  : '';
                        $promotedTweets[$creative->tweet_id]['type_platform'] = 'TWEET';
                        $promotedTweets[$creative->tweet_id]['content'] = $creative->full_text;
                        $fulltextlen = mb_strlen($creative->full_text);
                        if ($fulltextlen > 120) {
                            $corte = mb_stripos($creative->full_text, ' ', 100); //hecho porque no siempre tienen mas de 100 char los tweets
                            $corte = ($corte) ? $corte : 100;
                        } else {
                            $corte = $fulltextlen;
                        }
                        $promotedTweets[$creative->tweet_id]['name'] = mb_substr($creative->full_text, 0, $corte);
                        $promotedTweets[$creative->tweet_id]['banner'] = $mediaurl;
                        $promotedTweets[$creative->tweet_id]['media_type'] = $mediatype;
                        $promotedTweets[$creative->tweet_id]['url'] = $url;
                        $promotedTweets[$creative->tweet_id]['metadata'] = ['tweet' => $creative];
                    }
                    $previewTweets = $twitterapi->get('accounts/' . $adAccount_data['account_platform_id'] . '/tweet_previews', $tweetParams);
                    foreach ($previewTweets->getBody()->data   as $preview) {
                        $promotedTweets[$preview->tweet_id]['preview'] = isset($preview->preview) ? $preview->preview  : '';
                    }

                    $i = 0;
                    $tweets = [];
                }
            }

            persistAd($promotedTweets, $infoCredentials,  $adAccount_data, $infoCredentials['platform']);

            //

        } catch (Exception $e) {
            /*
        [0] => __construct
        [1] => getErrors
        [2] => __wakeup
        [3] => getMessage
        [4] => getCode
        [5] => getFile
        [6] => getLine
        [7] => getTrace
        [8] => getPrevious
        [9] => getTraceAsString
        [10] => __toString
        )
        */
            echo "Error api twitter: " . $e->getMessage();
            echo "Error api twitter: " . $e->getCode();
            if (method_exists($e, 'getErrors')) {
                echo "Error api twitter: " . print_r($e->getErrors());
            } else {
                //     var_dump($e);

            }

            //die();
        }

        if (isset($requestParams['dataFields']['firstCharge']) && $requestParams['dataFields']['firstCharge'] == true) {
            execGoogleTask(['delaySeconds' => false, 'random' => rand(), 'action' => 'firstCharge', 'function' => 'function-adsconcierge-api'], $infoCredentials);
        }
    }

    public function get_account_adsets($adAccount_data, $infoCredentials, $twitterapi = FALSE, $apibasic = FALSE)
    {
        //$promotable_users = $twitterapi->get('accounts/'.$adAccountPlatformId.'/promotable_users', array('with_deleted'=> true));

        if (!isset($adAccount_data['ad_account_platformId'])) {
            return false;
        }
        $adAccountPlatformId = $adAccount_data['ad_account_platformId'];

        if (!$twitterapi) {
            $twitterapi = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
        }
        if (!$apibasic) {
            $apibasic = $this->loginApiBasic($infoCredentials);
        }


        try {

            //echo 'Ad Account = ' . $adAccount_data['ad_account_platformId'] . PHP_EOL. PHP_EOL;
            $account = new Account($adAccount_data['ad_account_platformId']);


            $account->read();
            // usar total count reduce el numero de request a 200 en 15 minutos TwitterAds\Fields\CampaignFields::WITH_TOTAL_COUNT => true,TwitterAds\Fields\CampaignFields::WITH_DRAFT=> false,
            $items = $account->getLineItems('', [TwitterAds\Fields\CampaignFields::COUNT => 200,  TwitterAds\Fields\CampaignFields::SORT_BY => 'created_at-desc']);
            $items->setUseImplicitFetch(true);



            //$cuentaTwBBDD = getAdAccount_Element($data['ad_account_id']);
            $itemDatabulk = [];
            foreach ($items as $item) {


                // print_r(get_class_methods($item));
                //  echo 'ID adset = '. $item->getId() . ' - Nombre = ' .$item->getName() . PHP_EOL;
                $itemData = [];
                $itemData['id'] =  $item->getId();
                $itemData['campaign_id'] =  $item->getCampaignId();
                $itemData['name'] =  $item->getName();
                $itemData['currency'] =  $item->getCurrency();
                $itemData['status'] =  $item->getEntityStatus();
                $itemData['effective_status'] =  $item->getEntityStatus();
                $itemData['chargeby'] =  $item->getPayBy();
                $itemData['objective'] =  $item->getObjective();
                $itemData['optimization_goal'] =  $item->getGoal();
                $itemData['targeting'] = [];
                $itemData['promoted_object'] = [];
                $itemData['start_time'] =  ($item->getStartTime()) ?  $item->getStartTime()->getTimestamp() : null;
                $itemData['end_time'] =   ($item->getEndTime()) ?  $item->getEndTime()->getTimestamp() : null;
                $itemData['lifetime_budget'] =   $item->getTotalBudgetAmountLocalMicro() / 1000000;
                $itemData['puja_valor'] =   $item->getBidAmountLocalMicro() / 1000000;
                $itemData['bid_strategy'] =   $item->getBidStrategy();
                $itemData['product_type'] =   $item->getProductType();
                $itemData['daily_budget'] =   null;
                $itemData['targeting']['publisher_platforms'] =   $item->getPlacements();
                $itemData['metadata'] =  $item->toArray();
                $itemDatabulk[] = $itemData;
            }
            persistAdset($itemDatabulk, $infoCredentials,  $adAccount_data,   $infoCredentials['platform']);
            ///echo 'preseti adstet'. print_r ( $itemDatabulk  , true);
        } catch (exception $e) {
            echo "Error api twitter: " . $e->getMessage();
            //die();
        }
    }

    public function adSet_create($adAccountData, $entity_platformId, $data, $requestParams)
    {

        if (isset($data['atomo'])) {
            foreach ($data['atomo'] as $atomo) {
                $this->adSet_create_unit($adAccountData, $entity_platformId, $atomo, $requestParams);
            }
        }
    }

    public function adSet_create_unit($adAccountData, $entity_platformId, $data, $requestParams)
    {
        try {

            if (isset($data['dataCloud']['dataAdSet'])) {

                $account = new Account($adAccountData['account_platform_id']);
                $account->read();

                $lineItem = new LineItem();

                $dataAdSet = $data['dataCloud']['dataAdSet'];
                foreach ($dataAdSet as $name => $value) {
                    if (method_exists($lineItem, ($method = 'set' . $name))) {
                        $lineItem->$method($value);
                    }
                }

                if ($lineItem->getCampaignId() == NULL) $lineItem->setCampaignId($entity_platformId);
                if ($lineItem->getName() == NULL) $lineItem->setName($dataAdSet['name']);
                if ($lineItem->getProductType() == NULL) $lineItem->setProductType(Enumerations::PRODUCT_PROMOTED_TWEETS);
                if ($lineItem->getPlacements() == NULL) $lineItem->setPlacements([Enumerations::PLACEMENT_ALL_ON_TWITTER]);
                if ($lineItem->getObjective() == NULL) $lineItem->setObjective($dataAdSet['optimization_goal']);
                if ($lineItem->getBidAmountLocalMicro() == NULL) $lineItem->setBidAmountLocalMicro($dataAdSet['bid_amount'] * 100);
                if ($lineItem->getEntityStatus() == NULL) $lineItem->setEntityStatus($dataAdSet['PAUSED']);

                $result = $lineItem->save();
                $fields['id_en_platform'] = $lineItem->getId();


                foreach ($dataAdSet['targeting'] as $key => $value) {
                    switch ($key) {
                        case 'age_min':
                            $targetingType = 'AGE';
                            $targetingValue = 'AGE_' . $dataAdSet['targeting']['age_min'] . '_TO_' . $dataAdSet['targeting']['age_max'];
                            break;
                        case 'interests':
                            $targetingType = 'INTEREST';
                            $targetingValue = $value;
                            break;
                        case 'geo_locations':
                            $targetingType = 'LOCATION';
                            $targetingValue = array_values($value)[0];
                            break;
                    }


                    $targetingCriteria = new TargetingCriteria();
                    $targetingCriteria->setLineItemId($fields['id_en_platform']);
                    $targetingCriteria->setOperatorType('EQ');
                    $targetingCriteria->setTargetingType($targetingType);
                    $targetingCriteria->setTargetingValue($targetingValue);
                    $targetingCriteria->save();
                }

                $requestParams['dataFields'] = $data;
                persistWrite('atomo', getAtomoPublicIdById($data['atomoId']), $requestParams['taskId'], 'adSet_create', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
                return $result;
            }
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getMessage() . PHP_EOL;
            print_r($e);
            persistWrite('atomo', getAtomoPublicIdById($data['atomoId']), $taskId,  'adSet_create_exception', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'errors' => $e->getErrors(), 'error_code' => $e->getCode()], $requestParams);
            exit;
        }
    }

    public function adset_update_field($adAccountData, $entityInPlatformId, $fields, $entity_publicId, $taskId, $requestParams)
    {

        try {

            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();
            $lineItem = $account->getLineItems($entityInPlatformId);

            $lineItem->setAdvertiserUserId = null;


            foreach ($fields as $name => $value) {
                if (method_exists($lineItem, ($method = 'set' . $name))) {
                    $lineItem->$method($value);
                }
            }
            $result = $lineItem->save();


            persistWrite('atomo', $entity_publicId, $taskId,  'adset_update_field', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getCode();
            print_r($e);
            persistWrite('atomo', $entity_publicId, $taskId,  'adset_update_field', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'errors' => $e->getErrors(), 'error_code' => $e->getCode()], $requestParams);
            exit;
        }
    }

    public function adset_status_to_active($adAccountData, $requestParams)
    {
        $fields = ['EntityStatus' => 'ACTIVE'];
        $this->adset_update_field($adAccountData,   $requestParams['entity_platformId'], $fields, $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
    }

    public function adset_delete($adAccountData, $entityInPlatformId, $entity_publicId, $taskId, $requestParams)
    {

        try {
            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();
            $lineItem = $account->getLineItems($entityInPlatformId);

            $result = $lineItem->delete();
            debugeo(['RESULTADO' => $result]);
            persistWrite('atomo', $entity_publicId, $taskId,  'adset_delete', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getMessage();
            persistWrite('atomo', $entity_publicId, $taskId,  'adset_delete', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }


    public function entity_update_field($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams)
    {
        try {
            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();

            $result = [];
            foreach ($data as $key => $value) {
                $targetingCriteria = new TargetingCriteria();
                $targetingCriteria->setLineItemId($entityInPlatformId);
                $targetingCriteria->setOperatorType('EQ');
                $targetingCriteria->setTargetingType($key);
                $targetingCriteria->setTargetingValue($value);
                $result[] = $targetingCriteria->save();
            }

            persistWrite('entity', $entity_publicId, $taskId, 'entity_update_field', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getMessage();
            persistWrite('entity', $entity_publicId, $taskId,  'entity_update_field', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function entity_update_geo($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams)
    {
        $this->entity_update_field($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams);
    }

    public function entity_update_language($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams)
    {
        $this->entity_update_field($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams);
    }

    public function entity_update_interests($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams)
    {
        $this->entity_update_field($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams);
    }

    public function entity_update_gender($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams)
    {
        //MALE or FEMALE
        $this->entity_update_field($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams);
    }

    public function entity_update_audience($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams)
    {
        $this->entity_update_field($adAccountData, $entityInPlatformId, $data, $entity_publicId, $taskId, $requestParams);
    }

    public function get_account_campaigns($adAccount_data, $infoCredentials, $twitterapi = FALSE, $apibasic = FALSE)
    {

        if (!isset($adAccount_data['account_platform_id'])) {
            return false;
        }
        $adAccountPlatformId = $adAccount_data['account_platform_id'];

        if (!$twitterapi) {
            $twitterapi = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
        }
        if (!$apibasic) {
            $apibasic = $this->loginApiBasic($infoCredentials);
        }


        try {
            if (VERBOSE) {
                echo 'Ad Account = ' . $adAccount_data['account_platform_id'] . PHP_EOL . PHP_EOL;
            }
            $account = new Account($adAccount_data['account_platform_id']);


            $account->read();
            // usar total count reduce el numero de request a 200 en 15 minutos TwitterAds\Fields\CampaignFields::WITH_TOTAL_COUNT => true,
            $campaigns = $account->getCampaigns('', [TwitterAds\Fields\CampaignFields::COUNT => 200, TwitterAds\Fields\CampaignFields::WITH_DRAFT => true, TwitterAds\Fields\CampaignFields::SORT_BY => 'created_at-desc']);
            $campaigns->setUseImplicitFetch(true);

            $campaignsData = [];
            $campaignLineitems = [];


            //$cuentaTwBBDD = getAdAccount_Element($data['ad_account_id']);
            $itemDatabulk = [];
            foreach ($campaigns as $item) {


                //    print_r( $campaignsData);
                if (VERBOSE) {
                    echo 'ID Campaign = ' . $item->getId() . ' - Nombre = ' . $item->getName() . PHP_EOL;
                }
                $itemData = [];
                $itemData['id'] =  $item->getId();
                $itemData['name'] =  $item->getName();
                $itemData['currency'] =  $item->getCurrency();
                $itemData['status'] =  $item->getEntityStatus();
                $itemData['effective_status'] =  $item->getEntityStatus();
                $itemData['start_time'] =  ($item->getStartTime()) ?  $item->getStartTime()->getTimestamp() : null;
                $itemData['stop_time'] =   ($item->getEndTime()) ?  $item->getEndTime()->getTimestamp() : null;
                $itemData['metadata'] = 'comentado'; // $item  ;

                $itemDatabulk[] = $itemData;
            }
            persistCampaign($itemDatabulk, $infoCredentials,  $adAccount_data,   $infoCredentials['platform']);
        } catch (exception $e) {
            echo "Error  get_account_campaigns api twitter: " . $e->getMessage() . print_r($e->getErrors(), true);
            //die();
        }
    }

    public function campaign_create($adAccountData, $entity_platformId, $data, $requestParams)
    {
        $adAccount = $adAccountData['account_platform_id'];
        try {
            if (isset($data['campaign'])) {

                $account = new Account($adAccount);
                $account->read();
                $campaign = new Campaign();

                $data['campaign']['FundingInstrumentId'] = 'j0vy7';
                $data['campaign']['DailyBudgetAmountLocalMicro'] = 1000000;
                $data['campaign']['EntityStatus'] = $data['campaign']['status'];
                $data['campaign']['StartTime'] = date('Y-m-d\TH:i:s\Z');

                foreach ($data['campaign'] as $name => $value) {
                    if (method_exists($campaign, ($method = 'set' . $name))) {
                        $campaign->$method($value);
                    }
                }

                $result = $campaign->save();
                $fields['id_en_platform'] = $campaign->getId();

                persistWrite('campaign', $requestParams['entity_publicId'], $requestParams['taskId'], 'campaign_create', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
                return $result;
            }
        } catch (Exception $e) {
            echo 'Twitter campaign SDK returned an error: ' . $e->getMessage() . PHP_EOL;
            print_r($e);
            persistWrite('campaign', $requestParams['entity_publicId'], $requestParams['taskId'],  'campaign_update_field', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function campaign_update_field($adAccountData, $entityInPlatformId, $fields, $entity_publicId, $taskId, $requestParams)
    {
        debugeo(['donde' => 'ENTRO  campaign_update_field', 'datos' => [$adAccountData, $entityInPlatformId, $fields, $entity_publicId, $taskId, $requestParams]]);

        try {
            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();
            $campaigns = $account->getCampaigns($entityInPlatformId);

            foreach ($fields as $name => $value) {
                if (method_exists($campaigns, ($method = 'set' . $name))) {
                    $campaigns->$method($value);
                }
            }
            $result = $campaigns->save();
            debugeo(['RESULTADO' => $result]);
            persistWrite('campaign', $entity_publicId, $taskId,  'campaign_update_field', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getMessage();
            persistWrite('campaign', $entity_publicId, $taskId,  'campaign_update_field', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function campaign_update_budget($adAccountData, $requestParams)
    {
        $fields = ['total_budget_amount_local_micro' => $requestParams['newBudget']];
        $this->campaign_update_field($adAccountData,   $requestParams['entity_platformId'], $fields, $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
    }

    public function campaign_status_to_active($adAccountData, $requestParams)
    {
        $fields = ['entity_status' => 'ACTIVE'];
        $this->campaign_update_field($adAccountData,   $requestParams['entity_platformId'], $fields, $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
    }

    public function campaign_status_to_pause($adAccountData, $requestParams)
    {
        $fields = ['entity_status' => 'PAUSED'];
        $this->campaign_update_field($adAccountData,   $requestParams['entity_platformId'], $fields, $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
    }

    public function campaign_status_to_stop($adAccountData, $requestParams)
    {
        $fields = ['entity_status' => 'PAUSED'];
        $this->campaign_update_field($adAccountData,   $requestParams['entity_platformId'], $fields, $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
    }

    public function campaign_status_to_archive($adAccountData, $requestParams)
    {
        $fields = ['entity_status' => 'PAUSED'];
        $this->campaign_update_field($adAccountData,   $requestParams['entity_platformId'], $fields, $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
    }

    public function campaign_delete($adAccountData, $entityInPlatformId, $entity_publicId, $taskId, $requestParams)
    {

        try {
            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();
            $campaigns = $account->getCampaigns($entityInPlatformId);

            $result = $campaigns->delete();
            debugeo(['RESULTADO' => $result]);
            persistWrite('campaign', $entity_publicId, $taskId,  'campaign_update_field', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getMessage();
            persistWrite('campaign', $entity_publicId, $taskId,  'campaign_update_field', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }


    public function get_creativities($ad_account, $infoCredentials)
    {
        // get_creativities($data['ad_account_id'], $infoCredentials);

        global  $dbconn_stats, $dbconn;

        $AdFields = AdFields::getInstance();
        $fields = $AdFields->getvalues();


        try {

            $account = new AdAccount('act_' . $ad_account);
            $ads = $account->getAds($fields);

            $stmtadset = $dbconn->prepare("INSERT INTO creatividades
                                                        ( id_en_platform,
                                                        user_id,
                                                        customer_id,
                                                        name,
                                                        platform,
                                                        campana_root,
                                                        campana_platform_id,
                                                        atomo_id,
                                                        platform_status,
                                                        metadata,
                                                        source )
                                                        values (
                                                            ?,
                                                            ?,
                                                            ?,
                                                            ?,
                                                            ?,
                                                            ?,
                                                        (SELECT id FROM campaigns_platform WHERE campaigns_platform.id_en_platform = ? and campaigns_platform.user_id = ? LIMIT 1),
                                                        (SELECT id FROM campaigns_platform_atomo WHERE id_en_platform = ? and user_id = ? LIMIT 1),?,?, 'IMPORTED')
                                                        on duplicate key update platform_status=?, metadata=?");

            echo "Creatividades -> " . count($ads) . PHP_EOL;

            foreach ($ads as $ad) {

                $ad = $ad->getData();
                echo ' Creatividades ' . $ad['name'] . ' id: ' . $ad['id'] . PHP_EOL;

                $stmtadset->bind_param("sisssssisissss", ...[
                    $ad['id'],
                    $infoCredentials['user_id'],
                    $infoCredentials["customer_id_default"],
                    $ad['name'],
                    $infoCredentials['platform'],
                    $infoCredentials["campaign_root_default"],
                    $ad['campaign']['id'],
                    $infoCredentials['user_id'],
                    $ad['adset_id'],
                    $infoCredentials['user_id'],
                    $ad['status'],
                    json_encode($ad),
                    $ad['status'],
                    json_encode($ad)
                ]);

                $stmtadset->execute();

                if ($stmtadset->error != "") {
                    printf("not inserted - Error: %s.\n", $stmtadset->error);
                }
                echo PHP_EOL;
            }
        } catch (Exception $e) {
            print_r($e);
        }
    }

    public function ad_update_field($adAccountData, $entity_platformId, $data, $entity_publicId, $taskId, $requestParams, $api, $apibasic)
    {
        try {
            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();

            $user = $apibasic->get("account/verify_credentials");
            $oldPromotedTweet = new PromotedTweet();
            $oldPromotedTweet->loadResource($entity_platformId);

            $resource = "accounts/" . $requestParams['ad_account_platformId'] . "/tweets";
            $params = ['tweet_ids' => $oldPromotedTweet->getTweetId(), 'tweet_type' => 'PUBLISHED'];

            $response = $account->getTwitterAds()->get($resource, $params);
            $oldTweet = $response->getBody()->data;

            $text = (isset($data['text'])) ? $data['text'] : $oldTweet[0]->full_text;
            $name = (isset($data['name'])) ? $data['name'] : $oldTweet[0]->name;
            $media_ids = $oldTweet[0]->media_ids;
            if (isset($data['image_url'])) {
                $media_ids = $this->ad_create_media($adAccountData, $entity_platformId, ['media' => $data['creative']['image_url']], $requestParams, $api);
            }

            $params = ['as_user_id' => $user->id, 'name' => $name, 'media_ids' => $media_ids];
            $tweetNew = Tweet::create($account, $text, $params);

            $promotedTweet = new PromotedTweet();
            $promotedTweet->setLineItemId($oldPromotedTweet->getLineItemId());
            $promotedTweet->setTweetId($tweetNew->id);
            $result = $promotedTweet->save();
            $result = $oldPromotedTweet->delete();

            persistWrite('creatividad', $entity_publicId, $taskId,  'ad_update_field', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getCode();
            print_r($e);
            persistWrite('creatividad', $entity_publicId, $taskId,  'ad_update_field', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function ad_delete($adAccountData, $entity_platformId, $entity_publicId, $taskId, $requestParams)
    {
        try {
            $account = new Account($requestParams['ad_account_platformId']);
            $account->read();
            $promotedTweet = new PromotedTweet();
            $promotedTweet->loadResource($entity_platformId);
            $result = $promotedTweet->delete();
            persistWrite('creatividad', $entity_publicId, $taskId,  'ad_delete', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
            return $result;
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getCode();
            print_r($e);
            persistWrite('creatividad', $entity_publicId, $taskId,  'ad_delete', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function creatividad_create($adAccountData, $entity_platformId, $data, $requestParams, $api, $apibasic)
    {
        if (isset($data['creatividades'])) {
            foreach ($data['creatividades'] as $creatividad) {
                $this->creatividad_create_unit($adAccountData, $entity_platformId, $creatividad, $requestParams, $api, $apibasic);
            }
        }
    }

    public function creatividad_create_unit($adAccountData, $entity_platformId, $data, $requestParams, $api, $apibasic)
    {

        try {
            if (isset($data['dataCloud']['creative'])) {
                $creative = $data['dataCloud']['creative'];
                $account = new Account($requestParams['ad_account_platformId']);
                $account->read();
                $user = $apibasic->get("account/verify_credentials");
                $lineItem = $account->getLineItems($entity_platformId);

                $params = ['as_user_id' => $user->id, 'name' => $creative['name']];

                if (isset($creative['image_url'])) {
                    $params['media_ids'] = $this->ad_create_media($adAccountData, $entity_platformId, $creative, $requestParams, $api);
                }

                $tweet = Tweet::create($account, $creative['body'], $params);

                $promotedTweet = new PromotedTweet();
                $promotedTweet->setLineItemId($lineItem->getId());
                $promotedTweet->setTweetId($tweet->id);
                $result = $promotedTweet->save();

                $fields['id_en_platform'] = $promotedTweet->getId();
                persistWrite('creatividad', getCreatividadPublicIdById($data['creativeId']), $taskId, 'creatividad_create', $fields, ['status' => 'ok', 'return' => $result], $requestParams);
                return $result;
            }
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getCode();
            persistWrite('creatividad', getCreatividadPublicIdById($data['creativeId']), $taskId, 'creatividad_create', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function ad_update_media($adAccountData, $entity_platformId, $data, $entity_publicId, $taskId, $requestParams, $api, $apibasic)
    {
        $this->ad_update_field($adAccountData, $entity_platformId, $data, $entity_publicId, $taskId, $requestParams, $api, $apibasic);
    }

    public function ad_create_media($adAccountData, $entity_platformId, $data, $requestParams, $api)
    {
        global $ENDPOINT_BASE;
        try {
            $url = str_replace('/home/pre.adsconcierge.com/adsconcierge-beta/storage/', $ENDPOINT_BASE, $data['image_url']);
            if (isset($data['image_url'])) {
                $media = $api->upload(['media' => $url]);

                return $media->media_id;
            }
        } catch (Exception $e) {
            echo PLATFORM_NAME . ' SDK returned an error: ' . $e->getCode();
            print_r($e);
            persistWrite('creatividad', $entity_publicId, $taskId,  'ad_create_media', $fields, ['status' => 'Exception', 'return' => $e->getMessage(), 'error_code' => $e->getCode(), 'errors' => $e->getErrors()], $requestParams);
            exit;
        }
    }

    public function retrieve_all($data, $infoCredentials)
    {

        if (VERBOSE) {
            echo 'Retrieve all entities for ' . $infoCredentials['user_name'] . ' ' . $infoCredentials['user_email'] . PHP_EOL;
        }

        $accountsArray = $this->get_adsAccounts($data, $infoCredentials);
        $random = rand();
        $numItems = count($accountsArray);
        $i = 0;
        foreach ($accountsArray as $adAccount) {


            /*	$adAccountData=	getAdAccount_DataByPlatformId($adAccount, $infoCredentials);
                  get_account_campaigns($adAccountData, $infoCredentials);
                  get_account_adsets($adAccountData, $infoCredentials);
                  get_account_ads($adAccountData, $infoCredentials);
         */

            /***
            // buscamos properties
            $event = array(  'auth_id' => $infoCredentials['id'], 'type' => 'get_properties','action' => 'get_account_properties', 'function' => 'function-facebook-api', 'subject_id' =>  $adAccount['id'], 'callchild'=> [ array('type' => 'get_campaigns')     ] );
                    execGoogleTask($event, $data);
            $event = array(  'auth_id' => $infoCredentials['id'], 'type' => 'get_properties','action' => 'get_account_campaigns', 'function' => 'function-tw-api', 'subject_id' =>  $adAccount['id'], 'callchild'=> [ ] );
    
                    execGoogleTask($event, $data);
             **/

            //delay de 10 minutos para que de tiempo a traerse las properties, los tiempos son arbitrarios y estimados en cuanto puede llevar ejecutarse la funcion
            //no se hace encadenado por si falla la tarea no entrar en un bucle infinito
            //echo 'get campa'.PHP_EOL;
            $event = array('delaySeconds' => false, 'random' => $random, 'action' => 'get_account_entities', 'entity_platformId' => $adAccount, 'ad_account_platformId' => $adAccount, 'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'entity_name' => 'ad_account');
            if (++$i === $numItems) {
                $event['dataFields']['firstevent'] = true;
            }
            execGoogleTask($event, $infoCredentials, $data);

            /*
            $event = array('delaySeconds'=>false, 'random'=>$random, 'action' => 'get_account_campaigns','entity_platformId' => $adAccount,'ad_account_platformId' => $adAccount, 'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'entity_name'=> 'ad_account'  );
                    //echo 'get ADSET '.PHP_EOL;
                //delay de 30 minutos para que de tiempo a traerse las campaigns
                    $event = array('delaySeconds'=>1800,  'random'=>$random, 'action' => 'get_account_adsets','entity_platformId' => $adAccount, 'ad_account_platformId' => $adAccount,  'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'work_entity_name'=> 'ad_account'  );
            execGoogleTask($event,$infoCredentials,$data);
                        // echo 'get ADS '.PHP_EOL;
                    //delay de 90 minutos para que de tiempo a traerse las get_account_adsets
                    $event = array('delaySeconds'=>5400,  'random'=>$random, 'action' => 'get_account_ads','entity_platformId' => $adAccount, 'ad_account_platformId' => $adAccount,   'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'work_entity_name'=> 'ad_account'  );
            execGoogleTask($event,$infoCredentials,$data);
     */
            //exit;
            //aqui va una tarea a las 3 horas para ejecutar los update de id's internos  (campana_root, customer_id, campanaplatform_id etc) en las tablas por si no dio tiempo a que exisitieran cuando se importaron
        }
    }

    public function retrieve_all_adsAccounts_stats($requestParams, $period = 'last_3d', $infoCredentials = [])
    {
        $adAccounts = getAllAdsAccounts_by_authPublicId($infoCredentials);
        $delay = 0;
        $random = rand();
        foreach ($adAccounts as $adAccountData) {
            //solo llamamos a stats por ads, y de ahí consolidamos en adsets y campanas, asi ahorramos api calls
            //'delaySeconds'=> $delay+=30,
            $event = array('random' => $random,  'action' => 'get_account_stats_ads', 'ad_account_publicId' => $adAccountData['public_id'], 'ad_account_platformId' => $adAccountData['account_platform_id'], 'periodEnum' => $period, 'entity_publicId' => $adAccountData['public_id'],  'entity_platformId' =>  $adAccountData['account_platform_id'],  'type' => 'get_account_stats', 'function' => FUNCTION_API_NAME, 'work_entity_name' => 'ad_account');
            execGoogleTask($event, $infoCredentials, $requestParams);
        }
    }

    public function get_adsAccounts($requestParams, $infoCredentials)
    {

        global $dbconn_stats, $dbconn;
        if (VERBOSE) {
            echo 'Search AdsAccounts ' . PHP_EOL;
            print_r([$infoCredentials]);
        }
        $twitterapi = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
        if (VERBOSE) {
            print_r($twitterapi);
        }
        //echo 'login1'.	print_r( $twitterapi , true);

        $accounts = $twitterapi->getAccounts();

        $apibasic = $this->loginApiBasic($infoCredentials);
        //print_r(get_class_methods($apibasic ) );
        $arr = array();

        foreach ($accounts as $account) {

            $account->read();
            $funding = $account->getFundingInstruments();
            $adaccount_name = $account->getName();
            $ad_accountid = $account->getId();
            $paramsActive = [];
            $arr[] = $ad_accountid;
            /** para llamar al api si no tenemos metodo wrappeado
          $activeTweets = $twitterapi->get('accounts/'.$ad_accountid.'/tracking_tags', $paramsActive);
          $activeTweets = $twitterapi->get('accounts/'.$ad_accountid.'/funding_instruments', $paramsActive);
             **/



            if (VERBOSE) {
                echo  'Id = ' . $ad_accountid . ' | ' . $account->getName() . ', Currency ' . $funding->getCollection()[0]->getCurrency() . ' , Status ' . $account->getApprovalStatus() . PHP_EOL;
            }
            $itemData = array(
                "id_en_platform" => $ad_accountid,   "account_platform_id" => $ad_accountid,  "account_id" => $ad_accountid, "salt" => $account->getSalt(),  "name" => $account->getName(),
                "timezone" => $account->getTimezone(),  "timezone_switch_at" => $account->getTimezoneSwitchAt(),  "created_at" => $account->getCreatedAt(),
                "updated_at" => $account->getUpdatedAt(), "deleted" => $account->getDeleted(), "status" => $account->getApprovalStatus(), "approval_status" => $account->getApprovalStatus(),
                "business_id" => $account->getBusinessId(), "business_name" => $account->getBusinessName(), "industry_type" => $account->getIndustryType(), "category" => $account->getIndustryType(),
                "getPromotableUsers" => $account->getPromotableUsers(), "funding_instrument" => $funding->getCollection(), "currency" => $funding->getCollection()[0]->getCurrency(),
                "features" => $account->getFeatures(), "PromotableUsers" => $account->getPromotableUsers(), "metadata" => null
            );

            persistAdAccount($infoCredentials['user_id'], $itemData, $infoCredentials);


            $this->get_account_properties($itemData, $infoCredentials, $twitterapi, $apibasic);
        }

        //return true;
        return $arr;
    }

    public function get_account_properties($adAccountData, $infoCredentials, $twitterapi = FALSE, $apibasic = FALSE)
    {


        if (!isset($adAccountData['account_platform_id'])) {
            echo 'no tengo account_platform_id  ';
            return false;
        }
        $adAccountPlatformId = $adAccountData['account_platform_id'];

        if (!$twitterapi) {
            echo 'no tengo twitter api';
            $twitterapi = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
        }
        if (!$apibasic) {
            echo 'no tengo twitter loginApiBasic api';
            $apibasic = $this->loginApiBasic($infoCredentials);
        }
        echo 'entro get_account_properties';

        try {
            //traemos las paginas, que twitter llama promotable_users
            $promotable_users = $twitterapi->get('accounts/' . $adAccountPlatformId . '/promotable_users', array('with_deleted' => true));
            foreach ($promotable_users->getBody()->data as $item) {
                //    hay que buscar el user porque no devuelven el nombre en el api promotable_users
                $vlookup = $apibasic->get('users/lookup', array('user_id' => $item->user_id));
                $itemData['metadata'] = '';
                $itemData['name'] = '(NULL)';
                $itemData['id_en_platform'] = $item->user_id;
                if (isset($vlookup[0])) {
                    $itemData['name'] = '@' . $vlookup[0]->screen_name . ' | ' . $vlookup[0]->name;
                    unset($vlookup[0]->status);
                    $itemData['metadata'] = $vlookup;
                }
                persistProperties($itemData, $infoCredentials,  $adAccountPlatformId,  'PAGE');
            }
            return true;
        } catch (Exception $e) {
            print_r($e);
        }
        return false;
    }

    /***
    todo
     ***/

    public function get_account_pixels($adAccountData, $infoCredentials)
    {
        echo 'no implementado';
        return false;

        if (VERBOSE) {
            echo 'SEARCH Pixels ' . PHP_EOL;
        }

        foreach ($adPixels as $adPixel) {

            $pixel = $adPixel->getData();
            //var_dump($pixel);
            //die();

            $itemData = array_intersect_key($adPixel->getData(), array_flip($fields));
            //$adPixelData = array_merge($adPixelData, $this->userPlatformData);
            $itemData['ad_account_platformId'] = $pixel['owner_ad_account']['account_id'];
            $itemData['id_en_platform'] = $adPixel->getData()['id'];
            $itemData['id'] = $adPixel->getData()['id'];
            $itemData['name'] = $adPixel->getData()['name'];
            /**  $adPixelData['status'] = 0; //todo: fixed in origin code
            $adPixelData['category'] = '-';
            $adPixelData['token'] = '-';
            $adPixelData['type'] = 'PIXEL';
             **/
            // $this->persistPropertiesAccounts->execute($adPixelData);
            //  print_r ( $itemData );

            persistPixel($itemData, $infoCredentials,  $pixel['owner_ad_account']['account_id']);
        }
    }
    function getStats($stat)
    {
        if ($stat instanceof stdClass) {
            foreach (get_object_vars($stat) as $key => $val) {
                if (is_array($val)) {
                    echo "\t\t\t\t\t " . $key . ': ' . $val[0] . PHP_EOL;
                } else {
                    echo "\t\t\t\t\t " . $key . ' 0' . PHP_EOL;
                }
            }
        } else if (is_array($stat)) {
            foreach ($stat as $s) {
                echo $s . PHP_EOL;
            }
        }
    }

    function get_stats_global($parentobject, $infoCredentials, $params,  $parentevent = '', $twitterapi = false, $apibasic = FALSE)
    {
        $fields = ['account_id', 'impressions', 'clicks', 'campaign_id', 'campaign_name', 'account_currency', 'buying_type', 'date_start', 'objective', 'reach', 'spend', 'inline_post_engagement', 'cost_per_action_type', 'cpc', 'cpm', 'website_ctr'];

        $metrics = array(
            AnalyticsFields::METRIC_GROUPS_BILLING,
            AnalyticsFields::METRIC_GROUPS_VIDEO,
            AnalyticsFields::METRIC_GROUPS_MEDIA,
            AnalyticsFields::METRIC_GROUPS_WEB_CONVERSIONS,
            //   AnalyticsFields::METRIC_GROUPS_MOBILE_CONVERSION,
            AnalyticsFields::METRIC_GROUPS_ENGAGEMENT
        );

        switch ($parentevent) {
            case 'get_stats_campaigns':
            case 'get_account_stats_campaigns':
                $fields = ['account_id', 'impressions', 'clicks', 'campaign_id', 'campaign_name', 'account_currency', 'buying_type', 'date_start', 'objective', 'reach', 'spend', 'inline_post_engagement', 'cost_per_action_type', 'cpc', 'cpm', 'website_ctr'];
                $entity = 'CAMPAIGN';
                break;
            case 'get_stats_ad':
                //$fields = ['ad_id, adset_id, impressions', 'campaign_id', 'account_id', 'account_currency', 'buying_type', 'campaign_name', 'clicks', 'conversions', 'date_start', 'date_stop', 'objective', 'reach', 'spend', 'inline_post_engagement', 'actions', 'wish_bid', 'ad_bid_type', 'social_spend', 'video_thruplay_watched_actions', 'video_play_actions', 'ad_bid_value'];
                $fields[] = 'ad_id';
                $fields[] = 'ad_name';
                $fields[] = 'adset_id';
                $fields[] = 'campaign_id';
                $entity = 'PROMOTED_TWEET';
                break;

            case 'get_stats_adSet':
                $fields[] = 'adset_name';
                $fields[] = 'adset_id';
                $entity = 'LINE_ITEM';
                // ['impressions', 'campaign_id', 'account_id', 'account_currency', 'clicks', 'conversions', 'date_start', 'date_stop', 'objective', 'reach', 'spend',  'ad_bid_type', 'video_play_actions', 'ad_bid_value', 'adset_name', 'adset_id'];
                break;
        }


        try {
            $params['start_time']->setTimezone(new DateTimeZone($parentobject->getTimezone()));
            $params['end_time']->setTimezone(new DateTimeZone($parentobject->getTimezone()));
            $interval = new DateInterval('P1D');
            $daterange = new DatePeriod($params['start_time'], $interval, $params['end_time']);
            $rows = [];
            $statbase = [
                'id_in_platform' => 0, 'ad_id' => '', 'adset_id' => '', 'ad_name' => '', 'campananame' => '', 'date' => '',
                'metrics_delivery' => [], 'metrics_costs' => [], 'metrics_engagement' => [], 'metrics_video' => [], 'metrics_conversion' => [],
                'metrics_rest' => [], 'cost' => 0, 'impressions' => 0, 'reach' => 0, 'clicks' => 0, 'engagements' => 0, 'cpc' => 0, 'cpm' => 0, 'ctr' => 0,
                'video_views' => 0, 'video_starts' => 0, 'video_completes' => 0, 'currency' => '', 'conversions' => 0, 'objective' => '', 'device' => '', 'placement' => '', 'platform_position' => '',
                'video_content_starts' => 0, 'video_views_100' => 0,      'video_views_25' => 0,      'video_views_50' => 0,      'video_views_75' => 0
            ];
            $rows = [];
            foreach ($daterange as $date) {
                $dias[] = $date->format('Y-m-d');
            }


            $job = [];
            foreach ($params['placements'] as $placement) {
                $job[$placement] = $parentobject->all_stats(
                    $params['entity_ids'],
                    $metrics,
                    array(
                        "entity" => $entity,
                        // "entity_ids" => $params['entity_ids'],
                        "segmentation_type" => "PLATFORMS",
                        "placement" => $placement,
                        AnalyticsFields::START_TIME => $params['start_time']->setTime(0, 0, 0),
                        AnalyticsFields::END_TIME => $params['end_time']->setTime(0, 0, 0),
                        AnalyticsFields::GRANULARITY => Enumerations::GRANULARITY_DAY
                        //                    AnalyticsFields::GRANULARITY => Enumerations::GRANULARITY_TOTAL
                    ),
                    true
                );
            }
            // print_r($job);
            $datos = [];
            foreach ($params['placements'] as $placement) {
                while ($job[$placement]->getStatus() == TwitterAds\Fields\JobFields::PROCESSING) {
                    //     echo 'Job is still processing. Waiting 5 more seconds' .PHP_EOL;
                    $job[$placement]->read();
                    sleep(5);
                }
                // echo 'finjob '. $placement ;

                if ($job[$placement]->getStatus() == TwitterAds\Fields\JobFields::SUCCESS) {
                    $result = gzfile($job[$placement]->getUrl());
                    $result = implode('', $result);

                    $entityStats =  json_decode($result)->data;
                    $metricKeys = ['impressions', 'billed_charge_local_micro', 'clicks', 'url_clicks', 'engagements'];
                    foreach ($entityStats as $statGroup) {

                        $id_en_platform = $statGroup->id;

                        foreach ($statGroup->id_data  as $stat) {
                            $device = $stat->segment->segment_name;
                            //echo 'finjob '. $id_en_platform  .' - '. $placement . ' - '. $device.PHP_EOL;
                            foreach ($dias as $idx =>  $dia) {
                                $sufix = $id_en_platform . $placement . $device;
                                $datos[$idx . $sufix] = $statbase;
                                $datos[$idx . $sufix]['date'] = $dia;
                                $datos[$idx . $sufix]['startdate'] = $dia;
                                $datos[$idx . $sufix]['placement'] = $placement;
                                $datos[$idx . $sufix]['device'] = self::MAPA_DEVICES[$device];
                                $datos[$idx . $sufix]['platform_position'] = $device;
                                $datos[$idx . $sufix]['id_in_platform'] = $id_en_platform;
                                $datos[$idx . $sufix]['ad_id'] = $id_en_platform;
                            }

                            foreach ($metricKeys  as $metrica) {
                                $imetrica = 0;
                                if (is_array($stat->metrics->{$metrica})) {
                                    foreach ($stat->metrics->{$metrica} as $key => $value) {
                                        switch ($metrica) {
                                            case 'billed_charge_local_micro':
                                                $datos[$key . $sufix][$metrica] = $value;
                                                break;
                                            default:
                                                $datos[$key . $sufix][$metrica] = $value;
                                                break;
                                        }
                                    }
                                }
                            }
                        }

                        //$stats = $stat->id_data[0]->metrics;

                    }
                }
            }
            //   $datos->setUseImplicitFetch(true);
            //       print_r(get_class_methods($parentobject));
            ///       print_r(get_class_methods($datos));


            $retorno = [];
            //  echo 'datos count '.count($datos).PHP_EOL;
            return $datos;
            foreach ($datos as $citem) {
                $retorno[] = $citem->getData();
            }
            return $retorno;
        } catch (Exception $e) {
            print_r($e->getMessage());
            print_r($e->getErrors());
            print_r($e->getTrace()[0]);
            $loggermessage = array('level' => 'Exception', 'category' => 'FbapiError', 'message' => $e->getMessage(), 'params' => json_encode($params),  'infoCredentials' => json_encode($infoCredentials));
            extLogger($loggermessage);
        }
    }

    function get_account_stats_ads($AdAccountObj, $data, $period = 'last_3d', $infoCredentials)
    {

        //echo 'get_account_stats_ads AdAccountObj '. PRINT_R( $AdAccountObj , true);
        if (VERBOSE) {
            echo 'get_account_stats_ads data ' . PRINT_R($data, true);
            echo 'get_account_stats_ads $infoCredentials ' . PRINT_R($infoCredentials, true);
            echo 'get_account_stats_ads $AdAccountObj ' . PRINT_R($AdAccountObj, true);
        }

        if (!isset($AdAccountObj['account_platform_id'])) {
            return false;
        }
        $adAccountPlatformId = $AdAccountObj['account_platform_id'];

        try {

            $twitterapi = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
            if (VERBOSE) {
                echo '$twitterapi ' . print_r($twitterapi, true);
            }

            $account = new Account($AdAccountObj['account_platform_id']);
            $account->read();
            // print_r(get_class_methods($account));
            //   exit;
            // $parentobject =  new AdAccount("act_" . $AdAccountObj['account_platform_id']  );

            $period = unifyPeriod('campaign', $infoCredentials['platform'], $period);

            $params = [
                'level' => 'ad',
                'date_preset' => $period,
                'time_increment' => '1',
                'breakdowns' => 'publisher_platform,platform_position,device_platform'
            ];
            //  'time_range' => array('since' => substr($data['start_date'], 0, 10), 'until' => substr($data['end_date'], 0, 10)),
            $message = 'Stats adset ad_account: ' . $AdAccountObj['account_platform_id'] . ' from ' . $data['start_date'] . ' to ' . $data['end_date'] . PHP_EOL;
            $error_message = ['add_acount' => $AdAccountObj];
            $startTime = new DateTime('90 Days ago');
            $startTime->setTimezone(new DateTimeZone($account->getTimezone()));
            $startTime->setTime(0, 0, 0);

            $endTime =  new DateTime('80 Days ago');
            $endTime->setTimezone(new DateTimeZone($account->getTimezone()));
            $endTime->setTime(0, 0, 0);

            $paramsActive = [
                'start_time' => $startTime->format('Y-m-d'),
                'end_time' => $endTime->format('Y-m-d'),
                'entity' => 'PROMOTED_TWEET',
                'entity' => 'PROMOTED_TWEET'

            ];


            $activeTweets = $twitterapi->get('stats/accounts/' . $AdAccountObj['account_platform_id'] . '/active_entities', $paramsActive);


            $items = $activeTweets->getBody()->data;
            echo '$activeTweets ' .   count($items) . PHP_EOL;
            $oncourseIds = [];
            $placements = [];
            /*
    [0] => stdClass Object
        (
            [entity_id] => 6qxeap
            [activity_start_time] => 2021-06-11T05:55:13Z
            [activity_end_time] => 2021-06-21T14:30:06Z
            [placements] => Array
                (
                    [0] => ALL_ON_TWITTER
                    [1] => PUBLISHER_NETWORK
                )

        )

    */

            if (count($items) < 1) {
                return true;
            }
            $i = 0;
            foreach ($items as $item) {
                $oncourseIds[] = $item->entity_id;
                foreach ($item->placements as $placement) {
                    $placements[$placement] = $placement;
                }
                if ($i == 20) {
                    // get_stats_global
                    $i = 0;
                    $oncourseIds = [];
                }
            }

            $params = ['entity_ids' =>  $oncourseIds, 'placements' => $placements, 'entity' => $paramsActive['entity'], 'start_time' => $startTime, 'end_time' =>  $endTime];
            $bulkdata = $this->get_stats_global($account, $infoCredentials, $params,  'get_stats_ad', $twitterapi);
            echo PHP_EOL . PHP_EOL . 'get_account_stats_ads  Qty items - tw API  ' . count($bulkdata) . PHP_EOL;

            persistStats_platform_Ads_day($infoCredentials, $infoCredentials['platform'], $AdAccountObj, $bulkdata);
        } catch (exception $e) {
            echo "Error api twitter: " . $e->getMessage();
            //die();
        }
    }

    function twitter_stats_generic($userid, $accountw, $kindentity, $itemsid_en_pl, $granular, $startDate, $endDate, $campaignsData = null)
    {

        $dates = dateRanges($startDate, $endDate, $granular);
        //print_r($dates);die;

        $metricas = [];


        foreach ($dates as $date) {

            if (is_string($itemsid_en_pl)) {
                $items = [$itemsid_en_pl];
            } else {
                $items = $itemsid_en_pl;
            }

            try {
                $stats = $accountw->all_stats(
                    $items,
                    array(
                        AnalyticsFields::METRIC_GROUPS_BILLING,
                        AnalyticsFields::METRIC_GROUPS_VIDEO,
                        AnalyticsFields::METRIC_GROUPS_MEDIA,
                        AnalyticsFields::METRIC_GROUPS_WEB_CONVERSIONS,
                        AnalyticsFields::METRIC_GROUPS_MOBILE_CONVERSION,
                        AnalyticsFields::METRIC_GROUPS_ENGAGEMENT
                    ),
                    array(
                        AnalyticsFields::ENTITY => $kindentity,
                        AnalyticsFields::START_TIME => $date[0],
                        AnalyticsFields::END_TIME => $date[1],
                        AnalyticsFields::GRANULARITY => Enumerations::GRANULARITY_TOTAL
                    )
                );
            } catch (exception $e) {
                echo "Error: " . $e->getMessage();
                continue;
            }

            foreach ($stats as $statsitem) {

                $statsData = cleanConversions($statsitem->id_data[0]->metrics);
                $name = $campaignsData[$statsitem->id]['name'];
                if ($kindentity == AnalyticsFields::PROMOTED_TWEET)
                    $name = $campaignsData[$statsitem->id]['ad_name'];

                $metricas[] = array_merge([
                    'entity' => $kindentity,
                    'startdate' => $date[0]->format('Y-m-d H:i:s'),
                    'enddate' => $date[1]->format('Y-m-d H:i:s'),
                    'id_in_platform' => $statsitem->id,
                    'id' => null,
                    strtolower($kindentity) . '_name' => $name,
                    'currency' => (isset($campaignsData[$statsitem->id]['currency']) ? $campaignsData[$statsitem->id]['currency'] : null),
                ], (array)$statsData);
            }
        }
        return $metricas;
    }

    // STATS functions
    function stats_campana_xaccount($twiiterApi, $infoCredentials, $data)
    {

        try {

            $cuentaTwBBDD = getAdAccount_Element($data['ad_account_id']);

            echo 'AdAccount Twitter - ' . $data['ad_account_id'] . PHP_EOL;

            $accountw = new Account($cuentaTwBBDD['account_id']);
            $accountw->read();

            $getFundingInstruments = $accountw->getFundingInstruments();

            $campaigns = $accountw->getCampaigns('');

            $campaigns->setUseImplicitFetch(true);

            $campaignsIds = array();
            $campaignsData = array();
            $campStruct = array();

            foreach ($campaigns as $campaign) {

                if ($campaign->effective_status != 'RUNNING') {
                    //continue;
                }

                $campaignsIds[] = $campaign->getId();

                $campaignsData[$campaign->getId()] = array(
                    'id' => $campaign->getId(),
                    'name' => $campaign->getName(),
                    'currency' => $campaign->getCurrency(),
                    'status' => $campaign->effective_status
                );

                if (count($campaignsIds) == 20) {
                    $campStruct[] = array(
                        'campaignsIds' => $campaignsIds,
                        'campaignsData' => $campaignsData
                    );
                    $campaignsIds = array();
                    $campaignsData = array();
                }
            }

            if (count($campaignsIds) > 0) {
                $campStruct[] = array(
                    'campaignsIds' => $campaignsIds,
                    'campaignsData' => $campaignsData
                );
            }

            $datos = array();

            foreach ($campStruct as $val) {

                $temp = $this->twitter_stats_generic($infoCredentials['user_id'], $accountw, AnalyticsFields::CAMPAIGN, $val['campaignsIds'], 'P1D', $data['start_date'], $data['end_date'], $val['campaignsData']);

                if (count($datos) > 0) {
                    $datos = array_merge($datos, $temp);
                } else {
                    $datos = $temp;
                }
            }

            helper_metrics_campana_day(
                $infoCredentials['platform'],
                $infoCredentials['user_id'],
                $infoCredentials['customer_id_default'],
                $datos,
                $cuentaTwBBDD['id'],
                $cuentaTwBBDD['account_id'],
                $infoCredentials
            );
        } catch (exception $e) {
            echo "Error api twitter: " . $e->getMessage();
            //die();
        }
    }


    public function handler(Request $request)
    {
        if ($request) {
            if (!isset($request['action'])) {
                echo 'No action defined in request';
                die();
            }
            if (!isset($request['auth_id']) && !isset($request['auth_publicId'])) {
                echo 'No AUTH ID defined in request';
                die();
            }
        }

        // conformamos el request
        $requestParams = array(
            'action' => isset($request['action']) ? $request['action'] : false,
            'auth_id' => isset($request['auth_id']) ? $request['auth_id'] : (isset($request['auth_publicId']) ? $request['auth_publicId'] : NULL), // 139 = 2ccd9123-d929-11eb-8d81-ac1f6b17ff4a
            'auth_publicId' => isset($request['auth_publicId']) ? $request['auth_publicId'] : (isset($request['auth_id']) ? $request['auth_id'] : NULL), // 139 = 2ccd9123-d929-11eb-8d81-ac1f6b17ff4a
            'ad_account_platformId' => isset($request['ad_account_platformId']) ? $request['ad_account_platformId'] : (isset($request['ad_account_id']) ? $request['ad_account_id'] : NULL),  // VACIO
            'ad_account_publicId' => isset($request['ad_account_publicId']) ? $request['ad_account_publicId'] : false,  // VACIO
            'work_entity_name' => isset($request['work_entity_name']) ? $request['work_entity_name'] : false,  // VACIO
            'entity_platformId' => isset($request['entity_platformId']) ? $request['entity_platformId'] : false,  // VACIO
            'entity_publicId' => isset($request['entity_publicId']) ? $request['entity_publicId'] : false,  // VACIO
            'periodEnum' => isset($request['periodEnum']) ? $request['periodEnum'] :  false, // opcional
            //compatiblidad
            'platform_object_id' => isset($request['ad_account_id']) ? $request['ad_account_id'] : null,  // VACIO
            'start_date' => isset($request['start_date']) ? $request['start_date'] : date('Y-m-d', strtotime(' - 7 day')), // opcional
            'end_date' => isset($request['end_date']) ? $request['end_date'] : date('Y-m-d', strtotime('today')), // opcional

            'callchild' => isset($request['callchild']) ? $request['callchild'] : [],  // opcional

            //WRITES
            'dataFields' => isset($request['dataFields']) ? $request['dataFields'] : [],  // opcional
            'taskId' => isset($request['taskId']) ? $request['taskId'] : false, // opcional
            'newBudget' => isset($request['newBudget']) ? $request['newBudget'] : ((isset($request['dataFields']) && isset($request['dataFields']['budget'])) ? $request['dataFields']['budget'] : false), // opcional
            'newBid' => isset($request['newBid']) ? $request['newBid'] : false, // opcional

        );

        $loggermessage = array('level' => 'info', 'source' => 'index', 'step' => 'inicio index', 'payload' => serialize($requestParams));
        extLogger($loggermessage);


        if ($requestParams['action'] == null) {
            echo 'NO ACTION SPECIFIED';

            $loggermessage = array('level' => 'info', 'source' => 'index', 'shortmessage' =>  'NO ACTION SPECIFIED', 'payload' => serialize($requestParams));
            extLogger($loggermessage);
            die();
        }




        $infoCredentials = getUserCredentialsByPublicId($requestParams['auth_publicId']);

        $requestParams['user_id'] = $user_id = $infoCredentials['user_id'];


        $api = $this->loginApi($infoCredentials, $infoCredentials['app_id'], $infoCredentials['app_secret']);
        $apibasic = $this->loginApiBasic($infoCredentials);

        // validamos token
        try {
            // check_token_isalive($infoCredentials);

        } catch (\Exception $e) {
            $loggermessage = array('level' => 'Exception', 'source' => 'GeneralException',  'shortmessage' =>  'token no validado',  'message' =>    $e->getMessage(),  'user_id' => $infoCredentials['user_id'], 'auth_id' => $infoCredentials['id'], 'payload' => serialize($requestParams),  'error' => serialize($e));
            extLogger($loggermessage);

            echo 'Exeption as ' . $e->getMessage() . PHP_EOL;
            exit;
        }


        if (isset($requestParams['ad_account_platformId'])  &&  $requestParams['ad_account_platformId'] != false) {
            $adAccountData =     getAdAccount_DataByPlatformId($requestParams['ad_account_platformId']);
        }
        if (!isset($adAccountData) &&  $requestParams['ad_account_publicId']) {
            echo 'ad_account_publicId' . PHP_EOL;
            $adAccountData =     getAdAccount_ByPublicID($requestParams['ad_account_publicId']);
        }

        switch ($requestParams['action']) {
            case "retrieve_all":
                $this->retrieve_all($requestParams, $infoCredentials);
                break;

            case "get_account_adsaccounts":
                $this->get_adsAccounts($requestParams, $infoCredentials);
                break;

            case "get_account_properties":
            case "get_properties":
                //to-do falla en fb

                //  get_account_properties($adAccountData, $infoCredentials);
                break;

            case "get_account_pixels":
            case "get_pixels":
                echo 'getpisexl';
                // no funciona todavia
                //  get_account_pixels($requestParams['ad_account_id'], $infoCredentials);
                break;


            case "get_account_entities":
                $random = ''; //rand();
                $event = array('random' => $random,  'action' => 'get_account_campaigns',  'entity_platformId' => $adAccountData['ad_account_platformId'], 'ad_account_platformId' => $adAccountData['ad_account_platformId'], 'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'entity_name' => 'ad_account');
                execGoogleTask($event, $infoCredentials, $requestParams);

                $event = array('delaySeconds' => 1000,   'random' => $random, 'action' => 'get_account_adsets', 'entity_platformId' => $adAccountData['ad_account_platformId'], 'ad_account_platformId' => $adAccountData['ad_account_platformId'], 'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'entity_name' => 'ad_account');
                execGoogleTask($event, $infoCredentials, $requestParams);

                //creo task get_account_ads
                $event = array('delaySeconds' => 2400, 'random' => $random,  'action' => 'get_account_ads', 'entity_platformId' => $adAccountData['ad_account_platformId'], 'ad_account_platformId' => $adAccountData['ad_account_platformId'], 'type' => 'get_entity_data', 'function' => FUNCTION_API_NAME, 'entity_name' => 'ad_account');
                execGoogleTask($event, $infoCredentials, $requestParams);

                break;

            case "get_account_campaigns":
            case "get_campaigns":
                $this->get_account_campaigns($adAccountData, $infoCredentials);
                break;

            case "get_adsets":
            case "get_account_adsets":
                $this->get_account_adsets($adAccountData, $infoCredentials);
                break;

            case "get_account_ads":
                $this->get_account_ads($adAccountData, $infoCredentials);
                break;
                // TODO
            case "get_creativities":
                $this->get_creativities($adAccountData, $infoCredentials);
                break;

                // STATS
                // TODO
            case "get_stats_campaigns":
            case "get_account_stats_campaigns":
                get_account_stats_campaigns($adAccountData, $requestParams, 'last_30d', $infoCredentials);
                break;

                // TODO
            case "get_stats_adSet":
            case "get_account_stats_adsets":
                get_account_stats_adSets($adAccountData, $requestParams, 'last_7d', $infoCredentials);
                break;

            case "get_stats_ad":
            case "get_account_stats_ads":
                echo 'get ads stats';
                $this->get_account_stats_ads($adAccountData, $requestParams, 'last_7d', $infoCredentials);  //to-do el periodo que venga desde el request
                break;

            case "retrieve_stats_all":
            case "retrieve_all_accounts_stats":
                $this->retrieve_all_adsAccounts_stats($requestParams, 'last_3d', $infoCredentials, null);
                break;

                /**
            wrappers de updates
                 ***/
            case "entity_update_geo":
                $this->entity_update_geo($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
                break;

            case "entity_update_language":
                $this->entity_update_language($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
                break;

            case "entity_update_interests":
                $this->entity_update_interests($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
                break;

            case "entity_update_gender":
                $this->entity_update_gender($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
                break;

            case "entity_update_audience":
                $this->entity_update_audience($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
                break;
                /**
            fin wrappers de updates
                 ***/


                /**
            campaign writes
                 ***/
            case "campaign_update_fields":
                $this->campaign_update_field(
                    $adAccountData,
                    $requestParams['entity_platformId'],
                    $requestParams['dataFields'],
                    $requestParams['entity_publicId'],
                    $requestParams['taskId'],
                    $requestParams
                );
                break;

            case "campaign_update_budget":
                $retorno = $this->campaign_update_budget($adAccountData, $requestParams);
                break;

            case "campaign_status_to_active":
                $retorno = $this->campaign_status_to_active($adAccountData, $requestParams);
                break;

            case "campaign_status_to_pause":
                $retorno = $this->campaign_status_to_pause($adAccountData, $requestParams);
                break;

            case "campaign_status_to_stop":
                $retorno = $this->campaign_status_to_stop($adAccountData, $requestParams);
                break;

            case "campaign_status_to_archive":
                $retorno = $this->campaign_status_to_archive($adAccountData, $requestParams);
                break;

            case "campaign_delete":
                $retorno = $this->campaign_delete($adAccountData, $requestParams['entity_platformId'],  $requestParams['entity_publicId'], $requestParams['taskId'],  $requestParams);
                break;

            case "campaign_create":
                $retorno = $this->campaign_create($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams);
                break;

                /***
    
            fin campaign write
    
                 ***/

                /**
            adSet writes
                 ***/
            case "atomo_update_fields":
                $this->adset_update_field($adAccountData,   $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams);
                break;

            case "atomo_update_budget":
                adset_update_budget($adAccountData, $requestParams);
                break;

            case "atomo_status_to_active":
                $this->adset_status_to_active($adAccountData, $requestParams);
                break;

            case "atomo_status_to_pause":
                adset_status_to_pause($adAccountData, $requestParams);
                break;

            case "atomo_status_to_stop":
                adset_status_to_stop($adAccountData, $requestParams);
                break;

            case "atomo_status_to_archive":
                adset_status_to_archive($adAccountData, $requestParams);
                break;

            case "atomo_delete":
                $this->adset_delete($adAccountData, $requestParams['entity_platformId'], $requestParams['entity_publicId'],  $taskId, $requestParams);
                break;

            case "atomo_create":
                $this->adSet_create($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams);
                break;

                /***
    
            fin adSet write
    
                 ***/

                /**
            ad writes
                 ***/
            case "ad_update_fields":
                $this->ad_update_field($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams, $api, $apibasic);
                break;

            case "ad_update_budget":
                //No existe en twitter
                //ad_update_budget($adAccountData, $requestParams);
                break;

            case "ad_status_to_active":
                ad_status_to_active($adAccountData, $requestParams);
                break;

            case "ad_status_to_pause":
                $this->ad_delete($adAccountData, $requestParams['entity_platformId'], $requestParams['entity_publicId'], $taskId, $requestParams);
                break;

            case "ad_status_to_stop":
                $this->ad_delete($adAccountData, $requestParams['entity_platformId'], $requestParams['entity_publicId'], $taskId, $requestParams);
                break;

            case "ad_status_to_archive":
                $this->ad_delete($adAccountData, $requestParams['entity_platformId'], $requestParams['entity_publicId'], $taskId, $requestParams);
                break;

            case "ad_delete":
                $this->ad_delete($adAccountData, $requestParams['entity_platformId'], $requestParams['entity_publicId'], $taskId, $requestParams);
                break;

            case "ad_create":
                $this->creatividad_create($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams, $api, $apibasic);
                break;

            case "ad_update_media":
                $this->ad_update_media($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams['entity_publicId'], $requestParams['taskId'], $requestParams, $api, $apibasic);
                break;

            case "ad_create_media":
                $this->ad_create_media($adAccountData, $requestParams['entity_platformId'], $requestParams['dataFields'], $requestParams, $api);
                break;
                /***
            fin ad write
                 ***/


            default:
                echo "NO ACTION FOUND";
        }
    }
}
