<?php namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use EasyWeChat;
use EasyWeChat\Message\News;
use Redirect, Auth;
use EasyWeChat\Foundation\Application;
use App\modules\module\WcuserModule;
use App\modules\module\RelyModule;

class WechatController extends Controller {

    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function serve()
    {
        $server = EasyWeChat::server();;
        $server->setMessageHandler(function($message){

            $is_wcuser = WcuserModule::getWcuser('*', $message->FromUserName);

            /*如果数据库中没有这个用户就添加*/
            while (empty($is_wcuser)) {
                $wcuser = WcuserModule::addWcuser($message->FromUserName);
                $is_wcuser = WcuserModule::getWcuser('*', $message->FromUserName);
            }

            /*如果数据库中有这个用户，但是他之前取消关注过*/
            while($is_wcuser['subscribe'] ==0){
                WcuserModule::updateSubscribe(1,$is_wcuser['id']);
                $is_wcuser = WcuserModule::getWcuser('*', $message->FromUserName);
            }

            /*判断事件类型*/
            if ($message->MsgType == 'event') {//事件
                if ($message->Event=='subscribe') {//关注事件
                    return $this->subscribe();
                }elseif ($message->Event=='unsubscribe') {//取消关注事件
                    WcuserModule::updateSubscribe(0,$message->FromUserName);
                }
            }elseif ($message->MsgType == 'text') {
                $chat = WcuserModule::addChat($is_wcuser['id'], $message->Content);
                return $this->text($message->Content);
            }
        });

        $response = $server->serve();

        return  $response;
    }


    /*
        关注自动回复
     */
    public function subscribe()
    {
        $SubscribeRely = RelyModule::getRely(0);//获取关注时自动回复的内容
        if (is_array($SubscribeRely) && !empty($SubscribeRely)) {
            return $SubscribeRely['answer'];
        } else {
            return "嗨!你好！感谢关注中大南方PC志愿者服务队微信公众号！";
        }
    }

    /*
        微信消息回复
     */
    public static function text($content)
    {
        $AlltextRely = RelyModule::getRely(1);//获取用户发送消息时自动回复的内容
        if (is_array($AlltextRely) && !empty($AlltextRely)) {
           return $AlltextRely['answer'];
        }elseif ($content=='骏哥哥好帅') {
            $news = new News([
                'title'       => 'PC仔信息登记',
                'description' => 'PC仔申请通道',
                'url'         => action('WechatController@pcer'),
                'image'       => 'https://mmbiz.qlogo.cn/mmbiz/OEpqnOUyYjMcqqpJBRh2bhFDWTXUL3fdT54e7HTLTzEyEfzXk8XTUJQsrFx5pHvC7v6eSDNLicse62Hvpwt4o0A/0',
            ]);
            return $news;
        }else {
            //获取精确搜索内容
            $full_match = RelyModule::getFullMatch($content);
            if (empty($full_match) && !is_array($full_match)) {
                //获取模糊匹配内容
                $half_match = RelyModule::getHalfMatch($content);
                if (!empty($half_match) && is_array($half_match)) {
                    return $half_match['answer'];
                }
            }
            return $full_match['answer'];
        }
        
    }
    /**
     * 网页授权登录进入报修页面
     * @author JokerLinly
     * @date   2016-08-26
     * @param  Application $app     
     * @param  Request     $request [description]
     * @return [type]               [description]
     */
    public function pchelp(Request $request)
    {
        $options = [
            'debug'  => true,
            'app_id'  => env('WECHAT_APPID'),
            'secret'  => env('WECHAT_SECRET'),
            'token'   => env('WECHAT_TOKEN'),
            'aes_key' => env('WECHAT_AES_KEY'),

            'oauth' => [
                'scopes'   => ['snsapi_base'],
                'callback' => '/pchelp',
            ],
        ];
        $app = new Application($options);
        $oauth = $app->oauth;
        // 未登录
        if (empty($_SESSION['wechat_user']) && !$request->has('code')) {
          return $oauth->redirect();
        }

        $user = $oauth->user();
        $openid = $user->getId();
        $request->session()->put('wechat_user', $user->toArray());
        $_SESSION['wechat_user'] = $user->toArray();
                
        return Redirect::action('Ticket\HomeController@index',array('openid'=>$openid));
    }

    /**
     * 网页授权登录进入订单页面
     * @author JokerLinly
     * @date   2016-08-29
     * @param  Request    $request [description]
     * @return [type]              [description]
     */
    public function mytickets(Request $request)
    {
        $options = [
            'debug'  => true,
            'app_id'  => env('WECHAT_APPID'),
            'secret'  => env('WECHAT_SECRET'),
            'token'   => env('WECHAT_TOKEN'),
            'aes_key' => env('WECHAT_AES_KEY'),

            'oauth' => [
                'scopes'   => ['snsapi_base'],
                'callback' => '/mytickets',
            ],
        ];
        $app = new Application($options);
        $oauth = $app->oauth;
        // 未登录
        if (empty($_SESSION['wechat_user']) && !$request->has('code')) {
          return $oauth->redirect();
        }

        $user = $oauth->user();
        $openid = $user->getId();
        $_SESSION['wechat_user'] = $user->toArray();
        $request->session()->put('wechat_user', $user->toArray());
        
        return Redirect::action('Ticket\TicketController@index',array('openid'=>$openid));
    }

    /**
     * 网页授权登录进入PC仔登记页面
     * @author JokerLinly
     * @date   2016-09-13
     * @param  Request    $request [description]
     * @return [type]              [description]
     */
    public function pcer(Request $request)
    {
        $options = [
            'debug'  => true,
            'app_id'  => env('WECHAT_APPID'),
            'secret'  => env('WECHAT_SECRET'),
            'token'   => env('WECHAT_TOKEN'),
            'aes_key' => env('WECHAT_AES_KEY'),

            'oauth' => [
                'scopes'   => ['snsapi_base'],
                'callback' => '/comeon',
            ],
        ];
        $app = new Application($options);
        $oauth = $app->oauth;
        // 未登录
        if (empty($_SESSION['wechat_user']) && !$request->has('code')) {
          return $oauth->redirect();
        }

        $user = $oauth->user();
        $openid = $user->getId();
        $_SESSION['wechat_user'] = $user->toArray();
        $request->session()->put('wechat_user', $user->toArray());
        
        return Redirect::action('Member\HomeController@getAddPcer',array('openid'=>$openid));
    }

}
