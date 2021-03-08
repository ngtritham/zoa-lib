<?php

namespace ZSL;

class ZaloOa {
    private $use_proxy = FALSE;
    private $default_proxy_url = '';

    private $api_url = 'https://openapi.zaloapp.com/v2.0/oa/';
    private $zns_api_url = 'https://business.openapi.zalo.me/';
    private $api_oauth_url = 'https://oauth.zaloapp.com/v3/';
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Adtima-ZSL/0.0.1';
    private $oa_id = '';
    private $access_token = null;
    
    public function __construct($configs=[])
    {
        if (isset($configs['use_proxy']) && $configs['use_proxy'] === TRUE) {
            $this->use_proxy = TRUE;
        }

        if (isset($configs['access_token']) && !empty($configs['access_token'])) {
            $this->access_token = $configs['access_token'];
        }
    }

    // /**
    //  * @param $configs
    //  */
    // public function setConfigs($configs)
    // {
    //     $this->access_token = !empty($configs['access_token']) ? $configs['access_token'] : NULL;
    //     $this->oa_id = !empty($configs['oa_id']) ? $configs['oa_id'] : NULL;
    //     $this->oa_secret = !empty($configs['oa_secret']) ? $configs['oa_secret'] : NULL;
    //     $this->app_id = !empty($configs['app_id']) ? $configs['app_id'] : NULL;
    //     $this->app_secret = !empty($configs['app_secret']) ? $configs['app_secret'] : NULL;
    //     $this->proxy = !empty($configs['proxy']) ? $configs['proxy'] : FALSE;
    // }

    /* ________________________________________________ PRIVATE ________________________________________________ */
    
    /**
     * @param $method
     * @param $url
     * @param NULL $params
     * @return bool|mixed|string
     */
    private function cUrl($method, $url, $params = NULL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // set method and params
        if (!empty($params)) {
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            } else {
                $url = $url . '&' . http_build_query($params);
            }
        }
        if ($this->use_proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->default_proxy_url);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response) {
            $response = json_decode($response, TRUE, 512, JSON_BIGINT_AS_STRING);
            return $response;
        } else {
            return FALSE;
        }
    }

    /**
     * @param $method
     * @param $action
     * @param $params
     * @return bool|mixed|string
     */
    private function callApi($method, $action, $params = NULL)
    {
        $url = $this->api_url . $action . '?access_token=' . $this->access_token;
        return $this->cUrl($method, $url, $params);
    }

    /**
     * @param $method
     * @param $action
     * @param $params
     * @return bool|mixed|string
     */
    private function callZNSApi($method, $action, $params = NULL)
    {
        $url = $this->zns_api_url . $action . '/template?access_token=' . $this->access_token;
        return $this->cUrl($method, $url, $params);
    }

    /**
     * @param $number
     * @return bool|mixed|string
     */
    private static function checkValidPhone($number) {
        $number  = preg_replace("/[^0-9]/", "", $number);
        if (!preg_match('/^(841[2689]|01[2689]|84[39785]|0[39785])[0-9]{8}$/', $number)) return '';
        return $number;
    }

    /* _________________________________________________ PUBLIC _________________________________________________ */

    /**
     * @param $zalo_id_by_oa
     * @return bool|mixed|string
     */
    public function getProfile($zalo_id_by_oa)
    {
        $params = [
            'data' => json_encode(
                [
                    'user_id' => $zalo_id_by_oa
                ]
            ),

        ];
        return $this->callApi('GET', 'getprofile', $params);
    }

    /**
     * @param $zalo_id_by_oa
     * @return bool|mixed|string
     */
    public function getListProfile($phones)
    {
        $return_data = [];

        foreach($phones as $phone) {
            $valid_phone  = $this->checkValidPhone($phone);
            if ($valid_phone) {
                $valid_phone = trim($valid_phone);
                $res    = $this->getProfile($valid_phone);
                $info   = $this->getInfo($res);
                $return_data[] = [
                    'phone_number'  => $valid_phone,
                    'status_follow' => $info['status'],
                    'zalo_id'       => $info['zalo_id'],
                    'zalo_name'     => $info['zalo_name'],
                    'zalo_avatar'   => $info['zalo_avatar'],
                ];
            }
        }

        return $return_data;
    }

    public function getInfo($data) {
        $status_follow = 'Undefined';
        $zalo_id       = '';
        $zalo_name     = '';
        $avatar        = '';

        switch($data['error']) {
            case 0:
                $status_follow = 'Follow';
                $zalo_id       = $data['data']['user_id'];
                $zalo_name     = $data['data']['display_name'];
                $avatar        = $data['data']['avatar'];
                break;
            case -213:
                $status_follow = 'Not Follow';
                break;
            case -201:
                $status_follow = 'Invalid Phone Number';
                break;
            default:
                $status_follow = 'Undefined';
        }

        return [
            'status'      => $status_follow,
            'zalo_id'     => $zalo_id,
            'zalo_name'   => $zalo_name,
            'zalo_avatar' => $avatar
        ];
    }

    /**
     * @param $zalo_id_by_oa
     * @param $message
     * @return bool|mixed|string
     */
    public function sendMessage($zalo_id_by_oa, $message)
    {
        $params = array(
            'recipient' => array(
                'user_id' => $zalo_id_by_oa
            ),
            'message' => $message,
        );
        return $this->callApi('POST', 'message', $params);
    }

    /**
     * @param $zalo_id_by_oa
     * @param $text_message
     * @return bool|mixed|string
     */
    public function sendTextMessage($zalo_id_by_oa, $text_message)
    {
        $params = array(
            'recipient' => array(
                'user_id' => $zalo_id_by_oa
            ),
            'message' => array(
                'text' => $text_message
            ),
        );
        return $this->callApi('POST', 'message', $params);
    }

    /**
     * @param $zalo_id_by_oa
     * @param $elements
     * @return bool|mixed|string
     */
    public function sendListMessage($zalo_id_by_oa, $elements)
    {
        $params = array(
            'recipient' => array(
                'user_id' => $zalo_id_by_oa
            ),
            'message' => array(
                'attachment' => array(
                    'type' => 'template',
                    'payload' => array(
                        'template_type' => 'list',
                        'elements' => $elements
                    )
                )
            ),
        );
        return $this->callApi('POST', 'message', $params);
    }

    /**
     * @param $data
     * @return array
     */
    public static function createElementsMessageLink($data)
    {
        $elements = array();
        for ($i = 0; $i < count($data); $i++) {
            $default_action = [
                'type' => isset($data[$i]['type']) ? $data[$i]['type'] : 'oa.open.url',
            ];

            if ($default_action['type'] == 'oa.open.url') {
                $default_action['url'] = $data[$i]['url'];
            } else if ($default_action['type'] == 'oa.query.hide') {
                $default_action['payload'] = $data[$i]['payload'];
            } else if ($default_action['type'] == 'oa.open.phone') {
                $default_action['payload'] = [
                    'phone_code' => $data[$i]['phone_code']
                ];
            } else {    // SMS
                $default_action['payload'] = [
                    'content' => $data[$i]['content'],
                    'phone_code' => $data[$i]['phone_code']
                ];
            }

            $element = array(
                'title' => $data[$i]['title'],
                'subtitle' => $data[$i]['description'],
                'image_url' => $data[$i]['thumbnail'],
                'default_action' => $default_action
            );
            array_push($elements, $element);
        }
        return $elements;
    }

    /**
     * @return bool|mixed|string
     */
    public function getOAProfile()
    {
        return $this->callApi('GET', 'getoa');
    }

    /**
     * @return bool|mixed|string
     */
    public function getTagsOfOA()
    {
        return $this->callApi('GET', 'tag/gettagsofoa');
    }

    /**
     * @param $tag_name
     * @return bool|mixed|string
     */
    public function removeTagOfOA($tag_name) {
        $params = array(
            'tag_name' => $tag_name
        );
        return $this->callApi('POST', 'tag/rmtag', $params);
    }

    /**
     * @param $zalo_id_by_oa
     * @param $tag_name
     * @return bool|mixed|string
     */
    public function addTagFollower($zalo_id_by_oa, $tag_name) {
        $params = array(
            'user_id' => $zalo_id_by_oa,
            'tag_name' => $tag_name
        );
        return $this->callApi('POST', 'tag/tagfollower', $params);
    }

    /**
     * @param $zalo_id_by_oa
     * @param $tag_name
     * @return bool|mixed|string
     */
    public function removeTagFollower($zalo_id_by_oa, $tag_name) {
        $params = array(
            'user_id' => $zalo_id_by_oa,
            'tag_name' => $tag_name
        );
        return $this->callApi('POST', 'tag/rmfollowerfromtag', $params);
    }

    /**
     * @param $phone
     * @param $template_id
     * @param $elements
     * @return bool|mixed|string
     */
    public function sendZNS($phone, $template_id, $elements)
    {
        $params = array(
            'phone' => $phone,
            'template_id' => $template_id,
            'template_data' => $elements,
        );

        return $this->callZNSApi('POST', 'message', $params);
    }
}