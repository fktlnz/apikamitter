<?php


use \Model\Db; //Dbモデルをインポート

class Controller_Api extends Controller_Rest
{
    protected $format = 'json';
    public function get_login()
    {
        return $this->response(array(
            'IP-Address' => 'test',
            'foo' => Input::get('foo')
        ));
    }

//========================フロントから下記apiにリクエストが来る=========================//

    /**
     * ユーザー登録処理をする
     * 
     * @param none
     * @return array('res' => 'OK or NG' , 'error' => array(NGの場合にエラーメッセージを格納します))
    **/
    public function post_signup()
    {
        $error = array();
        //バリデーションの結果を保持する
        $json = array(
            'res' => 'NG',
            'error' => array(),
        );

        $model_signupform = Model_Signupform::forge();
        $signupform = Fieldset::forge('signupform');
        $signupform->add_model($model_signupform)->populate($model_signupform);

        if(Input::method() =='POST'){
            $validate = $signupform->validation();
            if($validate->run()){                
                //パスワードの一致を確認する（ここにいれるのでいいか。。？）
                if(Input::post('re_pass') !== Input::post('password')) {
                    $json['error']=array(
                        're_pass' => '『パスワード再入力』は『パスワード』と一致していません。',
                    );
                    return $this->response($json);
                }

                Log::debug('バリデーションに成功. DBにユーザー情報を格納します');
                $auth = Auth::instance(); //Authインスタンス生成
                try {
                    if($auth->create_user(Input::post('username'), Input::post('password'), Input::post('email'))){                        
                        $json['res']='OK';
                        return $this->response($json);
                        // メッセージ格納
                        // Session::set_flash('sucMsg','ユーザー登録が完了しました！');
                        // リダイレクト
                        // Response::redirect('member/mypage');
                    }else{
                        
                    }
                } catch(Exception $e) {
                    $json['error']=array(
                        're_pass' => 'このユーザーは登録できません('.$e->getMessage().')',
                    );
                    return $this->response($json);
                }
                

                
            }else {
                $errors = $validate->error();
                foreach( $errors as $field => $error )
                {
                    $json['error'][$field] = $error->get_message();
                }
                Log::debug('バリデーションに失敗しました:'.print_r($json, true));

                return $this->response($json);
            }
            
        }
    }

    const PASS_LENGTH_MIN = 6;

    /**
     * ログイン処理をする
     * 
     * @param none
     * @return array('res' => 'OK or NG' , 'error' => array(NGの場合にエラーメッセージを格納します))
    **/
    public function post_signin()
    {
        $error = array();
        //バリデーションの結果を保持する
        $json = array(
            'res' => 'NG',
            'error' => array(),
        );

        $signinform = Fieldset::forge('signinform');
        // addメソッドでformを生成、第一引数：name属性の値、第二引数：ラベルの文言、第三引数：色々な属性を配列形式で
        // add_ruleメソッドでバリデーションを設定（使えるルールはValidationクラスと全く同じ。Validationクラスを使っているので。）
        $signinform->add('username', 'ユーザー名', array('type'=>'text', 'placeholder'=>'ユーザー名'))
            ->add_rule('required')
            ->add_rule('min_length', 1)
            ->add_rule('max_length', 255);        

        $signinform->add('password', 'Password', array('type'=>'password', 'placeholder'=>'パスワード'))
            ->add_rule('required')
            ->add_rule('min_length', self::PASS_LENGTH_MIN);

        if(Input::method() =='POST'){
            $validate = $signinform->validation();
            if($validate->run()){
                Log::debug('バリデーションに成功. ログイン処理開始');
                $auth = Auth::instance(); //Authインスタンス生成
                try {
                    if($auth->login(Input::post('username'), Input::post('password'))){
                        // メッセージ格納
                        // Session::set_flash('sucMsg','ユーザー登録が完了しました！');
                        // リダイレクト
                        Log::debug('ログイン処理成功した。ユーザーID:'.Auth::get('id'));

                        // セッションにユーザーIDを格納
                        session_start();
                        session_regenerate_id( true );
                        $_SESSION["user_id"] = Auth::get('id');

                        $json['res']='OK';
                        return $this->response($json);
                    }else{
                        Log::debug('ログイン処理失敗した。');
                        $json['error']=array(
                            'msg' => 'ユーザー名またはパスワードが間違っています。',
                        );
                        return $this->response($json);
                    }
                } catch(Exception $e) {
                    $json['error']=array(
                        'msg' => 'ログインできませんでした('.$e->getMessage().')',
                    );
                    return $this->response($json);
                }
                

                
            }else {
                $errors = $validate->error();
                foreach( $errors as $field => $error )
                {
                    $json['error'][$field] = $error->get_message();
                }
                Log::debug('バリデーションに失敗しました:'.print_r($json, true));

                return $this->response($json);
            }
            
        }
    }

    /**
     * Titterアカウントの認証処理をする
     * ⇒AccessTokenおよびAccessTokenSecretを取得して、DBに格納する
     * 
     * @param none
     * @return $json
    **/
    public function get_certify()
    {
        //設定項目
        $api_key = "PL2EEcGoYzjCRcfY8TA48wE1n"; //API Key
        $api_secret="o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA"; //API Secret
        $callback_url="http://192.168.11.6:3000/#/home"; //Callback URL 

        //レスポンスする連想配列
        $json = array(
            'res' => 'NG',
            'msg' => '',
            'screen_name' => ''
        );
        Log::debug('get_certifyには来てる');
        
        if(isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])){
            Log::debug('oauth_token:'.$_GET['oauth_token']);
            Log::debug('oauth_verifier:'.$_GET['oauth_verifier']);
            //認証画面で承認した場合
            //アクセストーンを取得するための処理
            /*** [手順5] [手順5] アクセストークンを取得する ***/

            //[リクエストトークン・シークレット]をセッションから呼び出す
            session_start() ;
            $request_token_secret = $_SESSION["oauth_token_secret"];
            Log::debug('セッションに保存した$_SESSION["oauth_token_secret"] :'.print_r($_SESSION["oauth_token_secret"] , true));
            
            // リクエストURL
            $request_url = "https://api.twitter.com/oauth/access_token" ;

            // リクエストメソッド
            $request_method = "POST" ;

            // キーを作成する
            $signature_key = rawurlencode( $api_secret ) . "&" . rawurlencode( $request_token_secret ) ;

            // パラメータ([oauth_signature]を除く)を連想配列で指定
            $params = array(
                "oauth_consumer_key" => $api_key ,
                "oauth_token" => $_GET["oauth_token"] ,
                "oauth_signature_method" => "HMAC-SHA1" ,
                "oauth_timestamp" => time() ,
                "oauth_verifier" => $_GET["oauth_verifier"] ,
                "oauth_nonce" => microtime() ,
                "oauth_version" => "1.0" ,
            ) ;

            // 配列の各パラメータの値をURLエンコード
            foreach( $params as $key => $value ) {
                $params[ $key ] = rawurlencode( $value ) ;
            }

            // 連想配列をアルファベット順に並び替え
            ksort($params) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換
            $request_params = http_build_query( $params , "" , "&" ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode($request_params) ;

            // リクエストメソッドをURLエンコードする
            $encoded_request_method = rawurlencode( $request_method ) ;

            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;

            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . "&" . $encoded_request_url . "&" . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( "sha1" , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params["oauth_signature"] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params, "", "," ) ;

            // リクエスト用のコンテキストを作成する
            $context = array(
                "http" => array(
                    "method" => $request_method ,	//リクエストメソッド
                    "header" => array(	//カスタムヘッダー
                        "Authorization: OAuth " . $header_params ,
                    ) ,
                ) ,
            ) ;

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
            curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context["http"]["method"] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context["http"]["header"] ) ;	// ヘッダー
            curl_setopt( $curl, CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $response = substr( $res1, $res2["header_size"] ) ;	// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2["header_size"] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
            // $response = file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

            // $responseの内容(文字列)を$query(配列)に直す
            // aaa=AAA&bbb=BBB → [ "aaa"=>"AAA", "bbb"=>"BBB" ]
            $query = [] ;
            parse_str( $response, $query ) ;
            //$query => {'oauth_token(アクセストークン)'=>'xxx', 'oauth_token_secret(アクセストークンシークレット)'=>'xxx', 'user_id'=>'xxx', 'screen_name'}

            try{
                //認証が完了したアカウントの情報をmyaccountテーブルに保存する
                Log::debug('$query:'.print_r($query,true));
                $data = array();
                $data['screen_name'] = $query['screen_name'];
                $data['user_id'] = $_SESSION['user_id'];
                $data['access_token'] = $query['oauth_token'];
                $data['access_token_secret'] = $query['oauth_token_secret'];
                $data['create_date'] = date('Y:m:d h:i:s');
                $data['delete_flg'] = 0;

                $post = Model_Myaccount::forge();
                $post->set($data);
                $post->save();

                 $json['res']='OK';
                 $json['msg']='アカウントを認証しました！';
                 $json['screen_name']=$query['screen_name'];

                 return $this->response($json);
            }catch(Exception $e) {
                //例外発生
                $json['msg']='データベース接続に失敗しました！サーバーをご確認ください。';
                Log::debug('データベース接続に失敗:'.$e->getMessage());
                return $this->response($json);
            }
            
        }else{
            //一番初めはこの処理にはいる
            /*** [手順1] リクエストトークンの取得 ***/
    
            //[手順1-1]Keyを作成する
            
            // [アクセストークンシークレット] (まだ存在しないので「なし」)
            $access_token_secret = "" ;
    
            // エンドポイントURL
            $request_url = "https://api.twitter.com/oauth/request_token" ;
    
            // リクエストメソッド
            $request_method = "POST" ;
    
            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . "&" . rawurlencode( $access_token_secret );
    
            //[手順1-2]データを作成する
    
            // パラメータ([oauth_signature]を除く)を連想配列で指定
            $params = array(
                "oauth_callback" => $callback_url ,
                "oauth_consumer_key" => $api_key ,
                "oauth_signature_method" => "HMAC-SHA1" ,
                "oauth_timestamp" => time() ,
                "oauth_nonce" => microtime() ,
                "oauth_version" => "1.0" ,
            ) ;
    
            // 各パラメータをURLエンコードする
            foreach( $params as $key => $value ) {
                // コールバックURLはエンコードしない
                if( $key == "oauth_callback" ) {
                        continue ;
                }
    
                // URLエンコード処理
                $params[ $key ] = rawurlencode( $value ) ;
            }
    
            // 連想配列をアルファベット順に並び替える
            ksort( $params ) ;
    
            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params , "" , "&" ) ;
            
            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;
            
            // リクエストメソッドをURLエンコードする
            $encoded_request_method = rawurlencode( $request_method ) ;
            
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
            
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . "&" . $encoded_request_url . "&" . $request_params ;
    
    
            //[手順1-3]キーとデータを使って署名に変換する
    
            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( "sha1" , $signature_data , $signature_key , TRUE ) ;
    
            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;
    
            //[手順1-4]リクエストトークンを取得する
            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params["oauth_signature"] = $signature ;
    
            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params , "" , "," ) ;
    
            // リクエスト用のコンテキストを作成する
            $context = array(
                "http" => array(
                    "method" => $request_method , // リクエストメソッド (POST)
                    "header" => array(			  // カスタムヘッダー
                        "Authorization: OAuth " . $header_params ,
                    ) ,
                ) ,
            ) ;
    
            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;	// リクエストURL
            curl_setopt( $curl, CURLOPT_HEADER, true ) ;	// ヘッダーを取得する
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context["http"]["method"] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context["http"]["header"] ) ;	// リクエストヘッダーの内容
            curl_setopt( $curl, CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;
    
            // 取得したデータ
            $response = substr( $res1, $res2["header_size"] ) ;	// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2["header_size"] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)
            
            // if(!$response) {
            //     return $this->response(array(
            //         'response' => $response,
            //         'header' => $header
            //     ));
            // }
    
            // $responseの内容(文字列)を$query(配列)に直す
            // aaa=AAA&bbb=BBB → [ "aaa"=>"AAA", "bbb"=>"BBB" ]
            $query = [] ;
            parse_str( $response, $query );
    
            // セッション[$_SESSION["oauth_token_secret"]]に[oauth_token_secret]を保存する
            session_start() ;
            session_regenerate_id( true ) ;
            $_SESSION["oauth_token_secret"] = $query["oauth_token_secret"];
    
            return $this->response(array(
                'res' => 'OK',
                'response' => $response,
                'header' => $header,
                'url' => "https://api.twitter.com/oauth/authorize?oauth_token=".$query["oauth_token"]
            ));
            // ユーザーを認証画面へ飛ばす (毎回ボタンを押す場合)
            // URL を使う
            //Response::redirect("https://api.twitter.com/oauth/authenticate?oauth_token=".$query["oauth_token"], 'location', 301);
        }

    }

    /**
     * ログインユーザーが認証しているアカウントを取得する
     * 
     * @param none
     * @return　認証済のアカウントを配列で返します array('認証済アカウント1', '認証済アカウント2', '認証済アカウント3', ・・・) 
    **/
    public function get_getaccount()
    {
        session_start();
        $u_id = $_SESSION["user_id"];
        
        Log::debug('Userid:'.print_r($u_id,true));
        $screen_name = Db::get_screenName($u_id);
        if(count($screen_name) > 0){
            //認証済みアカウントが存在する場合
            if(empty($_SESSION['active_user'])){
                //初回アクセス時に入る。取得したscreen_nameの最初のアカウントをactive_userとする
                $_SESSION['active_user'] = $screen_name[0]['screen_name'];
            }

        }

        Log::debug('screen_name:'.print_r($screen_name,true));
        if($screen_name){

            return $this->response(array(
                'res' => 'OK',
                'screen_name' => $screen_name,                
            ));

        }else {

            return $this->response(array(
                'res' => 'NG',
                'screen_name' => null,                
            ));
            
        }
    }

    /**
     * screen_nameのtwitterプロフィールを取得する
     * 
     * @param none
     * @return　認証済のアカウントを配列で返します array('認証済アカウント1', '認証済アカウント2', '認証済アカウント3', ・・・) 
    **/
    public function get_gettwitterprofile()
    {
        session_start();
        $username = $_SESSION['active_user'];
        $twitter_profile = $this->getTwitterProfile($username);        

        Log::debug('twitter_profile:'.print_r($twitter_profile,true));
        if($twitter_profile){

            return $this->response(array(
                'res' => 'OK',
                'rst' => $twitter_profile,                
            ));

        }else {

            return $this->response(array(
                'res' => 'NG',
                'screen_name' => null,                
            ));
            
        }
    }

    /**
     * 認証しているアカウントをデータベースから論理削除する
     * 
     * @param none
     * @return　json
    **/
    public function get_deleteaccount()
    {
        session_start();
        $u_id = $_SESSION["user_id"];
        $screen_name = $_GET['screen_name'];
        if(isset($u_id) && isset($screen_name)){

            Log::debug('Userid@deleteaccount:'.print_r($u_id,true));
            $rst = Db::delete_account($u_id, $screen_name);
            Log::debug('delete_accountの結果:'.print_r($rst,true));
            if($rst){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'アカウントの連携を解除しました。',
                    'result' => $rst,                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アカウントの削除に失敗しました。ネットワークを確認してください。',
                    'result' => $rst,                
                ));
                
            }
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アカウントの削除に失敗しました。ネットワークを確認してください。',
                    'result' => $rst,                
                ));

        }
    }

    /**
     * 対象のTwitterアカウントの情報を取得してフロントに返す
     * 
     * @param none
     * @return　json
    **/
    public function get_getuserinfo()
    {
        session_start();
        $u_id = $_SESSION["user_id"];
        $screen_name = $_GET['screen_name'];

        //アカウントを切り替えたか
        //切り替えた場合はセッションをリセットする
        $IsChangeUser = ($screen_name === $_SESSION['active_user']);

        //===アクティブユーザーを変更する===///
        $_SESSION['active_user'] = $screen_name;
        Log::debug('アクティブユーザー変更：'.$_SESSION['active_user']);

        //アクティブユーザーを切り替えたときに
        //前のアカウントで保持したセッションをリセットする
        if(!empty($_SESSION) && $IsChangeUser){
            Log::debug('json_collection_liked_list:'.print_r(SESSION::get("json_collection_liked_list"),true));
            Log::debug('セッション削除！！！！！！！！！！！');
            SESSION::delete("json_collection_liked_list");
            unset($_SESSION["next_cursor"]);
            unset($_SESSION["skip_num"]);
            SESSION::delete("next_cursor");
            SESSION::delete("skip_num");
            Log::debug('json_collection_liked_list:'.print_r(SESSION::get("json_collection_liked_list"),true));
            Log::debug('next_cursor:'.print_r(SESSION::get("next_cursor"),true));
            Log::debug('skip_num:'.print_r(SESSION::get("skip_num"),true));
        }

        if(isset($u_id) && isset($screen_name)){

            Log::debug('Userid@getuserinfo:'.print_r($u_id,true));
            $rst = $this->getUserInfo($u_id, $screen_name);
            if($rst){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'アカウントを切り替えました。',
                    'active_user' => $_SESSION['active_user'],
                    'result' => $rst,                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アカウントの切り替えに失敗しました。ネットワークを確認してください。',
                    'active_user' => $_SESSION['active_user'],
                    'result' => $rst,                
                ));
                
            }
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アカウントの削除に失敗しました。ネットワークを確認してください。',
                    'result' => $rst,                
                ));

        }
    }

    /**
     * アクティブになっているアカウントを取得する
     * 
     * @param none
     * @return　アクティブアカウント
    **/
    public function get_getactiveuser()
    {
        session_start();
        return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'アクティブユーザーの取得に成功しました',
                    'active_user' => $_SESSION['active_user']
                ));
    }

    /**
     * ツイートを実行する
     * 
     * @param none
     * @return　json
    **/
    public function get_tweet()
    {
        $text = !empty($_GET['text']) ? $_GET['text'] : false;
        $s_id = !empty($_GET['id']) ? $_GET['id'] : false;
        Log::debug('text:'.print_r($text,true));
        if($text && $s_id){            
            $rst = $this->tweet($text);
            $rst = Db::set_scheduleDone($s_id); //スケジュールテーブルのIDを渡してツイート済みにする
            return $this->response(array(
                    'res' => 'OK',
                    'msg' => '＜予約投稿完了＞'.$text,
                    'rst' => $rst
                ));
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * 予約ツイートをDBに登録する
     * 
     * @param none
     * @return　アクティブアカウント
    **/
    public function get_savetweetschedule()
    {
        session_start();
        $id = !empty($_GET['id']) ? $_GET['id'] : false;
        $text = !empty($_GET['text']) ? $_GET['text'] : false;
        $time = !empty($_GET['time']) ? $_GET['time'] : false;
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        Log::debug('id:'.print_r($id,true));
        Log::debug('text:'.print_r($text,true));
        Log::debug('time:'.print_r($time,true));
        Log::debug('u_id:'.print_r($u_id,true));
        Log::debug('screen_name:'.print_r($screen_name,true));
        if($id && $text && $time && $u_id && $screen_name){   
            try{
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $data = array();
                $data['id'] = $id;
                $data['account_id'] = $u_info[0]['id'];
                $data['date'] = $time;
                $data['text'] = $text;
                $data['done_flg'] = 0;
                $data['delete_flg'] = 0;
                $data['created_at'] = date('Y:m:d h:i:s');

                $post = Model_Schedule::forge();
                $post->set($data);
                $rst = $post->save();//ツイート予約情報をDBに保存する
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'ツイートを予約しました。予約中は画面をリロードしないでください。',
                    'rst' => $rst
                ));

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }


    /**
     * 予約ツイート情報をDBから取得する
     * 
     * @param none
     * @return　json
    **/
    public function get_gettweetschedule()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        
        if($u_id && $screen_name){   
            try{
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $rst = Db::get_tweetschedule($u_info[0]['id']);

                if($rst){
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'ツイート予約情報を取得しました',
                        'rst' => $rst
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'サーバーエラー',
                        'rst' => $rst
                    ));
                }

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * セッションからいいねしたリストを取得する
     * 
     * @param none
     * @return　json
    **/
    public function get_getlikedlistsession()
    {        
        session_start();
        $json_collection_liked_list = Session::get('json_collection_liked_list');
        Log::debug('json_collection_liked_list:'.print_r($json_collection_liked_list,true));
        if($json_collection_liked_list !== null) {
            return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'いいね済のリストを取得しました',
                        'rst' => $json_collection_liked_list
                    ));
        }else{
            return $this->response(array(
                        'res' => 'NG',
                        'msg' => '',
                        'rst' => false
                    ));
        }

    }

    /**
     * セッションからいいねしたリストを取得する
     * 
     * @param none
     * @return　json
    **/
    public function get_getfollowedlistsession()
    {        
        session_start();
        $Session_followResult_Collection = Session::get('Session_followResult_Collection');
        Log::debug('Session_followResult_Collection:'.print_r($Session_followResult_Collection,true));
        if($Session_followResult_Collection !== null) {
            return $this->response(array(
                        'res' => `UPDATE`,
                        'msg' => 'フォロー済のリストを取得しました',
                        'rst' => $Session_followResult_Collection
                    ));
        }else{
            return $this->response(array(
                        'res' => 'NG',
                        'msg' => '',
                        'rst' => false
                    ));
        }

    }

    /* ================================
    # crontabfuncから呼ばれる関数
    =================================*/
    /**
     * 自動いいねを開始する
     * 
     * @param none
     * @return　json  [{'ツイートid'=>'', 'screen_name'=>'@のあとのアカウントID', 'created_at'=>'つぶやいた時間', 'text'=> 'ツイート文'}]
    **/
   
    public function get_startautolike()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        

        if(isset($u_id) && isset($screen_name)){   
            try{
                
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $account_id = $u_info[0]['id'];
                
                //いいねをつけるツイートIDの一覧を取得する
                $tweetIdList_forLike = $this->getTweetIdList_forLike($account_id);
                
                if(count($tweetIdList_forLike) > 0){          

                    $rst = $this->likeTweet($tweetIdList_forLike);

                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'test',
                        'rst' => $rst
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'NG',
                        'msg' => 'いいね対象が見つかりませんでした',
                        'rst' => $rst
                    ));
                }

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * 自動フォローを開始する
     * 
     * @param none
     * @return　json  [{'ツイートid'=>'', 'screen_name'=>'@のあとのアカウントID', 'created_at'=>'つぶやいた時間', 'text'=> 'ツイート文'}]
    **/
   
    public function get_startautofollow()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        // unset($_SESSION["next_cursor"]);
        // unset($_SESSION["skip_num"]);
        
        if(isset($u_id) && isset($screen_name)){   
            try{
                
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $account_id = $u_info[0]['id'];

                //登録したターゲットアカウントを取得する
                //[[0]=>[{'id' => 'アカウント登録ID', 'screen_name' => '@のあとのアカウントID'}] , [1]=> ・・・]
                $target_account = Db::get_useraccount($account_id, 0); //type 0:ターゲットアカウント
                Log::debug('target_account:'.print_r($target_account,true));

                //フォロワーサーチキーワードを取得する
                $search_keyword = Db::get_keyword($account_id, 0);//type 0:フォロワーサーチキーワード
                Log::debug('$search_keyword@DB:'.print_r($search_keyword,true));

                //ターゲットアカウント、フォロワーサーチキーワードのどちらかが空の場合は中止
                if(count($target_account)===0 || count($search_keyword)===0){
                    return $this->response(array(
                        'res' => 'NG',
                        'msg' => 'ターゲットアカウントとフォロワーサーチキーワードを登録してください',
                        'rst' => false
                    ));
                }



                //フォロー済アカウントを取得しておく（フォロー済であるアカウントをフォロワーターゲットリストから除外するため）
                $AlreadyFollowList = $this->getUseraccountArray($account_id, 1);//type 1:フォロー済アカウント

                //フォローした結果を格納する変数
                // [{
                //     'id' => $obj->id_str,//アカウントid
                //     'name' => $obj->screen_name, //スクリーンネーム
                //     'created_at' => date('Y:m:d h:i:s'), //フォローした日時
                //     'text' => $obj->description //プロフ内容
                // }];
                $followResult_Collection=array();

                foreach($target_account as $key => $val){
                    //今何週目のループかを保持する
                    //フォロー再開時に使用する
                    $key_num = $key;
                    if(Session::get('skip_num') !== null){ 
                        Log::debug('前回途中で中断しています　skip_num=>'.print_r($_SESSION['skip_num'],true));                       
                        //中断して再開するとき、もとのループまでスキップする
                        if($_SESSION['skip_num'] > $key) {
                            Log::debug('この回数スキップします＝＞'.print_r($key,true));
                            continue;
                        }
                    }
                    Log::debug('screen_name:'.print_r($val['screen_name'],true));

                    //フォロワーを取得する（↓戻り値）
                    //return $this->response(array(
                    //    'res' => 'OK/NG',
                    //    'msg' => 'メッセージ内容',
                    //    'rst' => $obj or false or 'request_limit'
                    //));
                    $result = $this->getFollower($val['screen_name']);
                    Log::debug('フォロワー取得した結果:'.print_r($result,true));

                    //フォロワーターゲットリスト
                    $follower_list = array();//['screen_name1','screen_name2','screen_name3',・・・]

                    if($result["res"] ==='OK' || $result["res"] ==='LIMIT'){
                        //全フォロワーを正常に取得しおわった　or　リクエスト上限に到達し、取得が途中で終わった場合
                        //取得に成功したとき                        
                        foreach($result["rst"] as $key_array => $val_array){
                            foreach($val_array as $key => $val){
                                //すでにフォロー済である場合は除外する
                                if(!in_array($val->screen_name, $AlreadyFollowList)){
    
                                    //取得したフォロワーリストを絞る
                                    //フォロワーサーチキーワードがプロフに含まれる場合にフォロー対象にする
                                    $IsFollowTarget = $this->checkFollowTarget($val->description, $search_keyword);
                                    Log::debug('フォロワーチェックした結果:'.print_r($IsFollowTarget,true));
                                    Log::debug('プロフ内容:'.print_r($val->description,true));
        
                                    //アンフォローリストにあるか確認する
        
        
                                    if($IsFollowTarget){
                                        array_push($follower_list, $val->screen_name);
                                    }
                                }else{
                                    Log::debug('すでにフォロー済です'.print_r($val->screen_name,true));
                                }
                            }

                        }
                        Log::debug('$follower_list_new:'.print_r($follower_list,true));     
                        
                        
                        
                        //フォローを開始
                        $dfresult = array(
                            'res' => '',
                            'msg' => '',
                            'rst' => null
                        );
                        foreach($follower_list as $key){
                            Log::debug('このアカウントをふぉろーします:'.print_r($key,true));
                            // array(
                            //     'res' => 'OK/NG',
                            //     'msg' => 'メッセージ',
                            //     'rst' => array(
                            //         'id' => $obj->id_str,
                            //         'name' => $obj->screen_name,
                            //         'text' => $obj->description,
                            //         'created_at' => $data['created_at']
                            //     )
                            // ); 

                            $dfresult = $this->doFollow($key);
                            if($dfresult['res']==='OK'){
                                $followResult_Collection[]=$dfresult['rst'];
                            }else if($dfresult['res']==='FOLLOWLIMIT'){
                                //フォロー制限になった場合はループを抜ける
                                break;
                            }
                        }   

                        
                        //セッションに格納したfollow済リストを$followResult_Collectionに追加する
                        $followResult_Collection_merged=array();//フォロー済のリストをマージした結果（これをフロントに返す）
                        $Session_followResult_Collection=array();
                        $Session_followResult_Collection = Session::get('Session_followResult_Collection');
                        Session::delete('Session_followResult_Collection');
                        Log::debug('Session_followResult_Collection セッションに入ってた=>:'.print_r($Session_followResult_Collection,true));
                        if($Session_followResult_Collection !== null){
                            //セッションに入っていた場合
                            Log::debug('$followResult_Collection 追加前=>:'.print_r($followResult_Collection,true));
                            $Session_followResult_Collection = array_merge($Session_followResult_Collection, $followResult_Collection);
                            Log::debug('$followResult_Collection 追加後=>:'.print_r($Session_followResult_Collection,true));

                            $setRst = Session::set('Session_followResult_Collection', $Session_followResult_Collection);
                            Log::debug('$setRst:'.print_r($setRst,true));
                            Log::debug('$followResult_Collection:'.print_r($Session_followResult_Collection,true));

                            $followResult_Collection_merged = $Session_followResult_Collection;

                        }else{
                            if(!empty($followResult_Collection)){

                                $setRst = Session::set('Session_followResult_Collection', $followResult_Collection);
                                Log::debug('$setRst:'.print_r($setRst,true));
                                Log::debug('$followResult_Collection:'.print_r($followResult_Collection,true));

                            }  
                            
                            $followResult_Collection_merged = $followResult_Collection;
                        }

                        
                        if($result["res"] ==='LIMIT'){
                            //getFollower内でフォロワー取得上限に達した場合は、再開したときに途中から始められるように
                            //1.スキップする回数 2.next_cursorを保持する
                            //フロント側で15分以上待機して、この関数に入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            $_SESSION['skip_num'] = $key_num;
                            //リクエスト上限に達した場合
                            return $this->response(array(
                               'res' => 'LIMIT',
                               'msg' => '15分後、フォロー再開します！',
                               'rst' => $followResult_Collection_merged
                            ));

                        }else if($dfresult["res"] ==='FOLLOWLIMIT'){
                            //doFollow内でフォロー上限に達した場合は、再開したときに途中から始められるように
                            //1.スキップする回数 2.next_cursorを保持する
                            //フロント側で3時間以上待機して、自動フォローに入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            $_SESSION['skip_num'] = $key_num;

                            return $this->response(array(
                               'res' => 'FOLLOWLIMIT',
                               'msg' => 'フォロー制限のため少し時間をおいてフォローを再開します',
                               'rst' => $followResult_Collection_merged
                            ));
                            

                        }else if($result["res"] ==='OK'){
                            //全フォロワーの取得に成功した場合
                            //中断から再開するために保持していたセッション変数をリセットする
                            if(isset($_SESSION["next_cursor"]) || isset($_SESSION["skip_num"])){
                                Log::debug('フォロー再開用セッション変数をリセットする前'); 
                                isset($_SESSION["next_cursor"]) ? Log::debug('$_SESSION["next_cursor"]:'.print_r($_SESSION["next_cursor"],true))  : Log::debug('next_cursorなし');
                                isset($_SESSION["skip_num"]) ? Log::debug('$_SESSION["skip_num"]:'.print_r($_SESSION["skip_num"],true))  : Log::debug('skip_numなし');
                                unset($_SESSION["next_cursor"]);
                                unset($_SESSION["skip_num"]);
                                Log::debug('フォロー再開用セッション変数をリセットしました'); 
                                isset($_SESSION["next_cursor"]) ? Log::debug('$_SESSION["next_cursor"]:'.print_r($_SESSION["next_cursor"],true))  : Log::debug('next_cursorなし');
                                isset($_SESSION["skip_num"]) ? Log::debug('$_SESSION["skip_num"]:'.print_r($_SESSION["skip_num"],true))  : Log::debug('skip_numなし');
                            }
                        }
                            

                    }else if($result["res"] ==='PRIVATE'){
                        //ユーザーが非公開のときスキップする
                        Log::debug('非公開ユーザーのためスキップします'); 
                        continue;
                    }else{

                        //取得に失敗したとき
                        if($result["rst"] ==='request_limit'){
                            

                        }else{
                            //上記以外の失敗の場合は、
                            //1.非公開のユーザーである
                            //ためそのまま続行する

                            return $this->response(array(
                                       'res' => 'NG',
                                       'msg' => 'testtesttest',
                                       'rst' => 'test'
                                    ));


                        }
                    
                    }
                    
                }

                Log::debug('$followResult_Collection_merged:'.print_r($followResult_Collection_merged,true));

                return $this->response(array(
                    'res' => 'OK',
                    'msg' => '自動フォロー完了！',
                    'rst' => $followResult_Collection_merged
                ));

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }


    /* =========================================================
    # 自動イイネキーワード・フォロワーサーチキーワード登録画面 共通
    ============================================================*/ 

    /**
     * キーワードをDBに保存する
     * 
     * @param none
     * @return　json
    **/
    public function post_savekeyword()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        $word_id = !empty(Input::post('id')) ? Input::post('id') : false;
        $like_word = !empty(Input::post('text')) ? Input::post('text') : false;
        $option = !empty(Input::post('option')) ? Input::post('option') : false;
        $type = !empty(Input::post('type')) ? Input::post('type') : false;
        Log::debug('word_id:'.print_r($word_id,true));
        Log::debug('like_word:'.print_r($like_word,true));
        Log::debug('option:'.print_r($option,true));
        Log::debug('type:'.print_r($type,true));

        if(isset($u_id) && isset($screen_name) && isset($word_id) && isset($like_word) && isset($option) && isset($type)){   
            try{
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                $id = $u_info[0]['id'];

                $data = array();
                $data['id'] = $word_id;
                $data['account_id'] = $id;
                $data['word'] = $like_word;
                $data['logic'] = $option;
                $data['type'] = $type; //0:フォロワーサーチ 1:いいねキーワード
                $data['delete_flg'] = 0;
                $data['created_at'] = date('Y:m:d h:i:s');

                $post = Model_Keyword::forge();
                $post->set($data);
                $rst = $post->save();//イイネキーワードをDBに保存する

                if($rst){
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'キーワードを保存しました',
                        'rst' => $rst
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'サーバーエラーです。管理者に問い合わせてください。',
                        'rst' => $rst
                    ));
                }

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * キーワードをDBから取得してフロントに戻す
     * 
     * @param none
     * @return　json
    **/
    public function get_getkeyword()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        $type = !empty(Input::get('type')) ? Input::get('type') : false; //0:フォロワーサーチ 1:いいねキーワード
        if(isset($u_id) && isset($screen_name) && isset($type)){   
            try{
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                $account_id = $u_info[0]['id'];

                $keyword_list = Db::get_keyword($account_id, $type);

                if($keyword_list){
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'キーワードを保存しました',
                        'rst' => $keyword_list
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'サーバーエラーです。管理者に問い合わせてください。',
                        'rst' => $keyword_list
                    ));
                }

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * キーワードをDBから論理削除する
     * 
     * @param none
     * @return　json
    **/
    public function get_deletekeyword()
    {

        $word_id = Input::get('word_id');
        if(isset($word_id)){

            $rst = Db::delete_keyword($word_id);
            Log::debug('delete_accountの結果:'.print_r($rst,true));
            if($rst){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'キーワードを削除しました',
                    'result' => $rst,                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'キーワードが削除できませんでした。ネットワークを確認してください。',
                    'result' => $rst,                
                ));
                
            }
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アカウントの削除に失敗しました。時間をおいて再度試してください',
                    'result' => $rst,                
                ));

        }
    }


    /* =========================================================================================
    # ユーザーアカウント登録　(0:ターゲットアカウント 1:フォロー済アカウント　2:アンフォローアカウント)
    ==========================================================================================*/
    /**
     * ユーザーアカウントをDBに保存する
     * 
     * @param none
     * @return　json
    **/
    public function post_saveuseraccount()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        $word_id = !empty(Input::post('id')) ? Input::post('id') : false;
        $username = !empty(Input::post('username')) ? Input::post('username') : false;
        $type = !empty(Input::post('type')) ? Input::post('type') : false;//0:ターゲットアカウント 1:フォロー済アカウント 2:アンフォローアカウント
        Log::debug('word_id:'.print_r($word_id,true));
        Log::debug('username:'.print_r($username,true));
        Log::debug('type:'.print_r($type,true));

        if(isset($u_id) && isset($screen_name) && isset($word_id) && isset($username) && isset($type)){   
            try{
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                $id = $u_info[0]['id'];

                $data = array();
                $data['id'] = $word_id;
                $data['account_id'] = $id;
                $data['screen_name'] = $username;
                $data['type'] = $type; //0:フォロワーサーチ 1:いいねキーワード
                $data['delete_flg'] = 0;
                $data['created_at'] = date('Y:m:d h:i:s');

                $post = Model_Useraccount::forge();
                $post->set($data);
                $rst = $post->save();//ユーザーアカウントをDBに保存する

                if($rst){
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'アカウントを保存しました',
                        'rst' => $rst
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'サーバーエラーです。管理者に問い合わせてください。',
                        'rst' => $rst
                    ));
                }

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * アカウントをDBから取得してフロントに戻す
     * 
     * @param none
     * @return　json
    **/
    public function get_getuseraccount()
    {        
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : false;
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : false;
        $type = !empty(Input::get('type')) ? Input::get('type') : false; //0:フォロワーサーチ 1:いいねキーワード
        if(isset($u_id) && isset($screen_name) && isset($type)){   
            try{
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                $account_id = $u_info[0]['id'];

                $useraccount_list = Db::get_useraccount($account_id, $type);

                if($useraccount_list){
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'ユーザーアカウントを取得しました',
                        'rst' => $useraccount_list
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'サーバーエラーです。管理者に問い合わせてください。',
                        'rst' => $useraccount_list
                    ));
                }

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
            
            
        }else {
            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。管理者に問い合わせてください',
                    'rst' => null
                ));
        }

    }

    /**
     * ユーザーアカウントをDBから論理削除する
     * 
     * @param none
     * @return　json
    **/
    public function get_deleteuseraccount()
    {

        $word_id = Input::get('word_id');
        if(isset($word_id)){

            $rst = Db::delete_useraccount($word_id);
            Log::debug('delete_accountの結果:'.print_r($rst,true));
            if($rst){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'ユーザーアカウントを削除しました',
                    'rst' => $rst,                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => '削除に失敗しました。ネットワークを確認してください。',
                    'rst' => $rst,                
                ));
                
            }
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => '削除に失敗しました。時間をおいて再度試してください',
                    'rst' => $rst,                
                ));

        }
    }

    /**
     * ユーザーアカウントがtwitterアカウントとして存在しているかチェックする
     * 
     * @param none
     * @return　json
    **/
    public function get_checkuseraccountexist()
    {

        $username = Input::get('screen_name');
        Log::debug('存在チェック:@'.print_r($username,true));
        if(isset($username)){

            $rst = $this->checkUserAccountExist($username);

            return $this->response(array(
                'res' => 'OK',
                'msg' => 'ユーザーアカウントのチェックが完了しました',
                'rst' => $rst,                
            ));
    
            
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。時間をおいて再度お試しください。',
                    'rst' => $rst,                
                ));

        }
    }
    
    
//========================ここから上記関数から呼ばれる関数=========================//

    /**
     * 対象のTwitterアカウントの情報を取得する(DBから取得)
     * 
     * @param none
     * @return　[0]['screen_name','access_token','access_token_secret']
    **/
    public function getUserInfo($u_id, $screen_name)
    {

        if(isset($u_id) && isset($screen_name)){

            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('get_userInfoの結果:'.print_r($u_info,true));
            
            return $u_info;
        }else {
            Log::debug('get_userInfoの結果:'.print_r('false',true));
            return false;
        }
    }

    /**
     * textをツイートする
     * アクティブになっているユーザーはSessionから取得する
     * 
     * @param text ツイート内容
     * @return　json
    **/
    public function tweet($text)
    {
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';

        if(isset($u_id) && isset($screen_name)){

            $u_info = Db::get_userInfo($u_id, $screen_name);

            // 設定
            $api_key = "PL2EEcGoYzjCRcfY8TA48wE1n"; //API Key
            $api_secret="o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA"; //API Secret
            $access_token = $u_info[0]['access_token'];		// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット[_TWITTER_OAUTH_1_]
            $request_url = 'https://api.twitter.com/1.1/statuses/update.json' ;		// エンドポイント
            $request_method = 'POST' ;

            // パラメータA (リクエストのオプション)
            $params_a = array(
                'status' => $text ,
            //	'media_ids' => "" ,	// 添付する画像のメディアID
            ) ;

            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

            // パラメータB (署名の材料用)
            $params_b = array(
                'oauth_token' => $access_token ,
                'oauth_consumer_key' => $api_key ,
                'oauth_signature_method' => 'HMAC-SHA1' ,
                'oauth_timestamp' => time() ,
                'oauth_nonce' => microtime() ,
                'oauth_version' => '1.0' ,
            ) ;

            // パラメータAとパラメータBを合成してパラメータCを作る
            $params_c = array_merge( $params_a , $params_b ) ;

            // 連想配列をアルファベット順に並び替える
            ksort( $params_c ) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params_c , '' , '&' ) ;

            // 一部の文字列をフォロー
            $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;

            // リクエストメソッドをURLエンコードする
            // ここでは、URL末尾の[?]以下は付けないこと
            $encoded_request_method = rawurlencode( $request_method ) ;
            
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
            
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params_c['oauth_signature'] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params_c , '' , ',' ) ;

            // リクエスト用のコンテキスト
            $context = array(
                'http' => array(
                    'method' => $request_method , // リクエストメソッド
                    'header' => array(			  // ヘッダー
                        'Authorization: OAuth ' . $header_params ,
                    ) ,
                ) ,
            ) ;

            // オプションがある場合、コンテキストにPOSTフィールドを作成する
            if ( $params_a ) {
                $context['http']['content'] = http_build_query( $params_a ) ;
            }

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;	// リクエストURL
            curl_setopt( $curl, CURLOPT_HEADER, true ) ;	// ヘッダーを取得
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $context['http']['method'] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER, $context['http']['header'] ) ;	// ヘッダー
            if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $context['http']['content'] ) ;	// リクエストボディ
            }
            curl_setopt( $curl, CURLOPT_TIMEOUT, 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $json = substr( $res1, $res2['header_size'] ) ;	// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです
            
            
            return $json;
        }else {
            return false;
        }
    }

    /**
     * $usernameのTwitter上のプロフィールを取得する()
     * 
     * @param none
     * @return　json
    **/
    public function getTwitterProfile($username)
    {

        $screen_name = $_SESSION['active_user'];
        $u_id = $_SESSION['user_id'];


        if(isset($u_id) && isset($screen_name)){

            $u_info = Db::get_userInfo($u_id, $screen_name);

            // 設定
            $api_key = "PL2EEcGoYzjCRcfY8TA48wE1n"; //API Key
            $api_secret="o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA"; //API Secret
            $access_token = $u_info[0]['access_token'];		// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/users/show.json' ;		// エンドポイント
            $request_method = 'GET' ;

            // パラメータA (オプション)
            $params_a = array(
        //       "user_id" => "1528352858",
        		"screen_name" => $username,
        //		"include_entities" => "true",
            ) ;

            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

            // パラメータB (署名の材料用)
            $params_b = array(
                'oauth_token' => $access_token ,
                'oauth_consumer_key' => $api_key ,
                'oauth_signature_method' => 'HMAC-SHA1' ,
                'oauth_timestamp' => time() ,
                'oauth_nonce' => microtime() ,
                'oauth_version' => '1.0' ,
            ) ;

            // パラメータAとパラメータBを合成してパラメータCを作る
            $params_c = array_merge( $params_a , $params_b ) ;

            // 連想配列をアルファベット順に並び替える
            ksort( $params_c ) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params_c , '' , '&' ) ;

            // 一部の文字列をフォロー
            $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;

            // リクエストメソッドをURLエンコードする
            // ここでは、URL末尾の[?]以下は付けないこと
            $encoded_request_method = rawurlencode( $request_method ) ;
        
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
        
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params_c['oauth_signature'] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params_c , '' , ',' ) ;

            // リクエスト用のコンテキスト
            $context = array(
                'http' => array(
                    'method' => $request_method , // リクエストメソッド
                    'header' => array(			  // ヘッダー
                        'Authorization: OAuth ' . $header_params ,
                    ) ,
                ) ,
            ) ;

            // パラメータがある場合、URLの末尾に追加
            if( $params_a ) {
                $request_url .= '?' . http_build_query( $params_a ) ;
            }

            // オプションがある場合、コンテキストにPOSTフィールドを作成する (GETの場合は不要)
        //	if( $params_a ) {
        //		$context['http']['content'] = http_build_query( $params_a ) ;
        //	}

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
            curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
        //	if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {		// GETの場合は不要
        //		curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
        //	}
            curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $json = substr( $res1, $res2['header_size'] ) ;		// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
            // $json = file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

            // JSONをオブジェクトに変換
            $obj = json_decode( $json );
            
            return $this->response($obj);
        }else {
            return false;
        }
    }


    /**
     * $account_idにおけるいいねツイートIDを取得する
     * 
     * @param $account_id：アカウントID
     * @return　いいねツイートIDリスト
    **/
    public function getTweetIdList_forLike($account_id)
    {

        //idlistマージ用　ANDのキーワードで絞るときに使う
        $idlist_and=array();
        $idlist_or=array();
        $idlist_all=array();
        $like_num = 3;//???????????? 何個取得する ？?????????????

        if(isset($account_id)){
            //登録したいいねキーワードを取得する
            //[i]['id', 'word', 'logic']
            $likeWord = Db::get_keyword($account_id, 1);
            Log::debug('likeWord:'.print_r($likeWord,true));

            $notLikeWord = Db::get_notlikeword($account_id);
            Log::debug('likeWord:'.print_r($notLikeWord ? 'notLikeキーワードがあります' : 'notLikeキーワードがありません',true));

            //AND登録キーワードの個数を保持する
            $logic_AND_num=0;
            $logic_OR_num=0;
            for($i=0;$i<count($likeWord);$i++){


                if($likeWord[$i]['logic'] === '0'){
                    //ANDのとき
                    $logic_AND_num = $logic_AND_num + 1;

                    $idlist = $this->getTweetIdList($likeWord[$i]['word'], $like_num, $notLikeWord);
                    
                    $idlist_and = array_merge($idlist_and , $idlist);

                }

                if($likeWord[$i]['logic'] === '1'){
                    //ORのとき
                    $logic_OR_num = $logic_OR_num + 1;
                    $idlist = $this->getTweetIdList($likeWord[$i]['word'], $like_num, $notLikeWord);
                    
                    $idlist_or = array_merge($idlist_or , $idlist);

                }


            }

            //ORで抽出したID内で重複しているものを削除する
            if($logic_OR_num > 0){

                Log::debug('$idlist_or 重複削除前:'.print_r($idlist_or,true));
                $idlist_or = array_unique($idlist_or);
                Log::debug('$idlist_or 重複削除後:'.print_r($idlist_or,true));

            }

            if($logic_AND_num > 1){
                //登録されているANDの個数が2個以上の場合に、重複しているものだけをピックアップ            
                //
                Log::debug('idlist_merge:'.print_r($idlist_and,true));
                Log::debug('$idlist_and（個数をカウント）:'.print_r(array_count_values($idlist_and),true));
                //重複しているIDだけを抽出
                $res = array_filter(array_count_values($idlist_and), function($v){return --$v;});
                Log::debug('$idlist_and（重複しているもののみピックアップ）:'.print_r($res,true));

                //｛[ID] => 個数｝　からIDのみを取り出す
                $idlist_and_filtered=array();
                foreach($res as $key => $val){

                    Log::debug('$key:'.$key.':'.$val);
                    $idlist_and_filtered[] = $key;
                }
                Log::debug('idlist_and_filtered:'.print_r($idlist_and_filtered,true));

                //
                $idlist_all = array_merge($idlist_and_filtered , $idlist_or);

            }else{
                $idlist_all = array_merge($idlist_and , $idlist_or);
            }
            
        }
        
        Log::debug('idlist_all:'.print_r($idlist_all,true));
        return $idlist_all;
    }

    /**
     * $wordを持つツイートIDを$num個 取得する
     * 
     * @param $word：いいねキーワード
     * @param $num: 取得するツイートIDの数
     * @param $notlikeword いいね除外キーワード
     * @return　成功したとき：いいねツイートIDリスト / 失敗したとき：false
    **/
    public function getTweetIdList($word, $num, $notlikeword)
    {
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';        

        if(isset($word) && isset($num)){

            
            $u_info = Db::get_userInfo($u_id, $screen_name);

            if(!$u_info) return false;

            
            // 設定
            $api_key = "PL2EEcGoYzjCRcfY8TA48wE1n"; //API Key
            $api_secret="o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA"; //API Secret
            $access_token = $u_info[0]['access_token'];		// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/search/tweets.json' ;		// エンドポイント
            $request_method = 'GET' ;

            // パラメータA (オプション)
            $params_a = array(
                "q" => $word,
        //		"geocode" => "35.794507,139.790788,1km",
        		"lang" => "ja",
        		"locale" => "ja",
        //		"result_type" => "popular",
        		"count" => $num,
        //		"until" => "2017-01-17",
        //		"since_id" => "643299864344788992",
        //		"max_id" => "643299864344788992",
        //		"include_entities" => "true",
            ) ;

            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

            // パラメータB (署名の材料用)
            $params_b = array(
                'oauth_token' => $access_token ,
                'oauth_consumer_key' => $api_key ,
                'oauth_signature_method' => 'HMAC-SHA1' ,
                'oauth_timestamp' => time() ,
                'oauth_nonce' => microtime() ,
                'oauth_version' => '1.0' ,
            ) ;

            // パラメータAとパラメータBを合成してパラメータCを作る
            $params_c = array_merge( $params_a , $params_b ) ;

            // 連想配列をアルファベット順に並び替える
            ksort( $params_c ) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params_c , '' , '&' ) ;

            // 一部の文字列をフォロー
            $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;

            // リクエストメソッドをURLエンコードする
            // ここでは、URL末尾の[?]以下は付けないこと
            $encoded_request_method = rawurlencode( $request_method ) ;
        
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
        
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params_c['oauth_signature'] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params_c , '' , ',' ) ;

            // リクエスト用のコンテキスト
            $context = array(
                'http' => array(
                    'method' => $request_method , // リクエストメソッド
                    'header' => array(			  // ヘッダー
                        'Authorization: OAuth ' . $header_params ,
                    ) ,
                ) ,
            ) ;

            // パラメータがある場合、URLの末尾に追加
            if( $params_a ) {
                $request_url .= '?' . http_build_query( $params_a ) ;
            }

            // オプションがある場合、コンテキストにPOSTフィールドを作成する (GETの場合は不要)
        //	if( $params_a ) {
        //		$context['http']['content'] = http_build_query( $params_a ) ;
        //	}

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
            curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
        //	if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {		// GETの場合は不要
        //		curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
        //	}
            curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $json = substr( $res1, $res2['header_size'] ) ;		// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
            // $json = file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

            // JSONをオブジェクトに変換
            $obj = json_decode( $json );


            //Log::debug('取得したツイートの数:'.print_r($obj->statuses[0]->id,true));
            //Log::debug('取得したツイート　obj:'.print_r($obj,true));

            //returnするidlist
            $idlist=array();
            
            for($i=0; $i<count($obj->statuses);$i++){
                $skip=false;
                Log::debug('loop start');
                Log::debug('ツイート文は:'.$obj->statuses[$i]->text);
                if($notlikeword){
                    //NOTlikeキーワードがある場合
                    foreach($notlikeword as $key => $val){
                        Log::debug('NOTキーワードは＝＞:'.print_r($val['word'],true));
                        if ( strpos( $obj->statuses[$i]->text, $val['word'] ) !== false ){
                            Log::debug('NOTキーワードが含まれてたよ'.$obj->statuses[$i]->text);
                            $skip=true;
                        }                        
                    }
                    //NOTキーワードを含んでいた場合はリストへの追加スキップ
                    if($skip) continue;
                    $idlist[] = strval($obj->statuses[$i]->id_str);
                }else{
                    //NOTlikeキーワードがない場合
                    $idlist[] = strval($obj->statuses[$i]->id_str);
                }
            }

            return $idlist;
        }
    }


    /**
     * $tweetIdList内のツイートIDのツイートにいいねする
     * 
     * @param $tweetIdList：いいねするツイートIDリスト
     * @return　成功したとき：$json_collection array('' => '', )いいねツイートIDリスト / 失敗したとき：false
    **/
    public function likeTweet($tweetIdList)
    {
        $json_collection = array();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';        

        if(isset($u_id) && isset($screen_name)){

            
            $u_info = Db::get_userInfo($u_id, $screen_name);

            if(!$u_info) return false;

            
            // 設定
            $api_key = "PL2EEcGoYzjCRcfY8TA48wE1n"; //API Key
            $api_secret="o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA"; //API Secret
            $access_token = $u_info[0]['access_token'];		// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/favorites/create.json';	// エンドポイント
            $request_method = 'POST';
            
                       
            $json_rtn = array(
                'id' => '',
                'name' => '',
                'created_at' => null,
                'text' => ''
            );
            for($i=0;$i<count($tweetIdList);$i++){

                // パラメータA (オプション)
                $params_a = array(
                    "id" => $tweetIdList[$i],
            //		"include_entities" => "true",
                ) ;
                    Log::debug('id:'.print_r($tweetIdList[$i],true));
                // キーを作成する (URLエンコードする)
                $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;
    
                // パラメータB (署名の材料用)
                $params_b = array(
                    'oauth_token' => $access_token ,
                    'oauth_consumer_key' => $api_key ,
                    'oauth_signature_method' => 'HMAC-SHA1' ,
                    'oauth_timestamp' => time() ,
                    'oauth_nonce' => microtime() ,
                    'oauth_version' => '1.0' ,
                ) ;
    
                // パラメータAとパラメータBを合成してパラメータCを作る
                $params_c = array_merge( $params_a , $params_b ) ;
    
                // 連想配列をアルファベット順に並び替える
                ksort( $params_c ) ;
    
                // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
                $request_params = http_build_query( $params_c , '' , '&' ) ;
    
                // 一部の文字列をフォロー
                $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;
    
                // 変換した文字列をURLエンコードする
                $request_params = rawurlencode( $request_params ) ;
    
                // リクエストメソッドをURLエンコードする
                // ここでは、URL末尾の[?]以下は付けないこと
                $encoded_request_method = rawurlencode( $request_method ) ;
            
                // リクエストURLをURLエンコードする
                $encoded_request_url = rawurlencode( $request_url ) ;
            
                // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
                $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;
    
                // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
                $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;
    
                // base64エンコードして、署名[$signature]が完成する
                $signature = base64_encode( $hash ) ;
    
                // パラメータの連想配列、[$params]に、作成した署名を加える
                $params_c['oauth_signature'] = $signature ;
    
                // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
                $header_params = http_build_query( $params_c , '' , ',' ) ;
    
                // リクエスト用のコンテキスト
                $context = array(
                    'http' => array(
                        'method' => $request_method , // リクエストメソッド
                        'header' => array(	// ヘッダー
                            'Authorization: OAuth ' . $header_params ,
                        ) ,
                    ) ,
                ) ;
    
                // パラメータがある場合、URLの末尾に追加 (POSTの場合は不要)
            //	if ( $params_a ) {
            //		$request_url .= '?' . http_build_query( $params_a ) ;
            //	}
    
                // オプションがある場合、コンテキストにPOSTフィールドを作成する
                if ( $params_a ) {
                    $context['http']['content'] = http_build_query( $params_a ) ;
                }
    
                // cURLを使ってリクエスト
                $curl = curl_init() ;
                curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
                curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
                curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
                curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
                curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
                curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
                if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {
                    curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
                }
                curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
                $res1 = curl_exec( $curl ) ;
                $res2 = curl_getinfo( $curl ) ;
                curl_close( $curl ) ;
    
                // 取得したデータ
                $json = substr( $res1, $res2['header_size'] ) ;	// 取得したデータ(JSONなど)
                // JSONをオブジェクトに変換
                $obj = json_decode( $json );

                if(empty($obj)){
                    //$objが空の場合は、

                }else{
                    if(empty($obj->errors)){                    
                        Log::debug('$obj:'.print_r($obj,true));
                        $json_rtn['id'] = $obj->id_str;
                        $json_rtn['name'] = $obj->user->screen_name;
                        $json_rtn['created_at'] = $obj->created_at;
                        $json_rtn['text'] = $obj->text;
    
    
                        Log::debug('$json_rtn:'.print_r($json_rtn,true));
                        $json_collection[]=$json_rtn;
    
                    }else{
                        Log::debug('いいね！に失敗しました=>:'.print_r($obj->errors,true));
                    }                

                }

            }

        }

        //セッションに格納したidlistを取得する
        //フロント側のHOME画面で画面更新されるとリストが空になるので、セッションで以前の結果を保持しておく
        $json_collection_liked_list=array();
        $json_collection_liked_list = Session::get('json_collection_liked_list');
        Session::delete('json_collection_liked_list');
        Log::debug('json_collection_liked_list セッションに入ってた=>:'.print_r($json_collection_liked_list,true));
        if($json_collection_liked_list !== null){
            //セッションに入っていた場合
            Log::debug('$json_collection 追加前=>:'.print_r($json_collection,true));
            // foreach($json_collection_liked_list as $key => $val){
            //     array_push($json_collection, $val);
            // }
            $json_collection_liked_list = array_merge($json_collection_liked_list, $json_collection);
            Log::debug('$json_collection 追加後=>:'.print_r($json_collection_liked_list,true));

            $setRst = Session::set('json_collection_liked_list', $json_collection_liked_list);
            Log::debug('$setRst:'.print_r($setRst,true));
            Log::debug('$json_collection:'.print_r($json_collection_liked_list,true));
            return $json_collection_liked_list;

        }else{
            if(!empty($json_collection)){

                $setRst = Session::set('json_collection_liked_list', $json_collection);
                Log::debug('$setRst:'.print_r($setRst,true));
                Log::debug('$json_collection:'.print_r($json_collection,true));
                return $json_collection;

            }else{
                //$json_collectionが空の場合は制限に引っかかっている可能性が高い
                return null;

            }
            
        }

    }


    /**
     * $usernameがtwitterアカウントとして登録されているか確認する
     * 
     * @param $username：@のあとのツイッターアカウント
     * @return　存在した：true / 存在しない：false
    **/
    public function checkUserAccountExist($username)
    {
        session_start();
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';        
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$screen_name:'.print_r($screen_name,true));
        if(isset($u_id) && isset($screen_name)){

            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            // 設定
            $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n' ;		// APIキー
            $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA' ;		// APIシークレット
            $access_token = $u_info[0]['access_token'];		// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/users/show.json' ;		// エンドポイント
            $request_method = 'GET' ;

            // パラメータA (オプション)
            $params_a = array(
        //      "user_id" => "1528352858",
        		"screen_name" => $username,
        //		"include_entities" => "true",
            ) ;

            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

            // パラメータB (署名の材料用)
            $params_b = array(
                'oauth_token' => $access_token ,
                'oauth_consumer_key' => $api_key ,
                'oauth_signature_method' => 'HMAC-SHA1' ,
                'oauth_timestamp' => time() ,
                'oauth_nonce' => microtime() ,
                'oauth_version' => '1.0' ,
            ) ;

            // パラメータAとパラメータBを合成してパラメータCを作る
            $params_c = array_merge( $params_a , $params_b ) ;

            // 連想配列をアルファベット順に並び替える
            ksort( $params_c ) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params_c , '' , '&' ) ;

            // 一部の文字列をフォロー
            $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;

            // リクエストメソッドをURLエンコードする
            // ここでは、URL末尾の[?]以下は付けないこと
            $encoded_request_method = rawurlencode( $request_method ) ;
        
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
        
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params_c['oauth_signature'] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params_c , '' , ',' ) ;

            // リクエスト用のコンテキスト
            $context = array(
                'http' => array(
                    'method' => $request_method , // リクエストメソッド
                    'header' => array(			  // ヘッダー
                        'Authorization: OAuth ' . $header_params ,
                    ) ,
                ) ,
            ) ;

            // パラメータがある場合、URLの末尾に追加
            if( $params_a ) {
                $request_url .= '?' . http_build_query( $params_a ) ;
            }

            // オプションがある場合、コンテキストにPOSTフィールドを作成する (GETの場合は不要)
        //	if( $params_a ) {
        //		$context['http']['content'] = http_build_query( $params_a ) ;
        //	}

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
            curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
        //	if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {		// GETの場合は不要
        //		curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
        //	}
            curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $json = substr( $res1, $res2['header_size'] ) ;		// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
            // $json = file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

            // JSONをオブジェクトに変換
            $obj = json_decode( $json );

            Log::debug('$obj:'.print_r($obj,true));
            if(empty($obj->errors)){                    
                Log::debug('$obj アカウントが存在しました:'.print_r($obj,true));
                return true;
            }else{
                Log::debug('$obj アカウントが存在しませんでした=>:'.print_r($obj,true));
                return false;
            }        
        }

    }


    /**
     * $usernameのフォロワーを取得する(20人取得する)
     * 
     * @param $username：フォロワーを取得したいツイッターアカウントの配列{@1, @2, @3, ・・・}
     * @return $this->response(array(
     *              'res' => '成功：OK　失敗：NG',
     *              'msg' => 'メッセージ内容',
     *              'rst' => '成功：レスポンスbody　失敗（フォロワー取得上限になった）:'request_limit'　失敗（取得できなかった）:false'
     *           ));
     * 
     * 
    **/
    public function getFollower($username)
    {
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';        
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$screen_name:'.print_r($screen_name,true));
        if(isset($u_id) && isset($screen_name)){

            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            //取得したフォロワー（users）を格納するオブジェクト
            $obj_collection = new ArrayObject();

            // 設定
            do {
                $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n' ;		// APIキー
                $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA' ;		// APIシークレット
                $access_token = $u_info[0]['access_token'];		// アクセストークン
                $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット
                $request_url = 'https://api.twitter.com/1.1/followers/list.json' ;		// エンドポイント
                $request_method = 'GET' ;            

                isset($_SESSION['next_cursor']) ? Log::debug('isset($_SESSION["next_cursor"])の値:'.print_r($_SESSION['next_cursor'],true)) : Log::debug('next_cursorなし');
                // パラメータA (オプション)
                $params_a = array(
            //      "user_id" => "1528352858",
                    "screen_name" => $username,
                    "cursor" => isset($_SESSION["next_cursor"]) ? $_SESSION["next_cursor"] : -1,//中断した場合は、$_SESSION['next_cursor']からページ情報を取得する。初期値:-1
                    // "cursor" => "1647003147843880227",//中断した場合は、$_SESSION['next_cursor']からページ情報を取得する。初期値:-1
                    "count" => "50", //いくつにするのが最適か？多すぎるとspam判定される。。
            //		"skip_status" => "true",
            //		"include_user_entities" => "true",
                ) ;
                Log::debug('cursor:'.print_r($params_a["cursor"],true));
                // キーを作成する (URLエンコードする)
                $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

                // パラメータB (署名の材料用)
                $params_b = array(
                    'oauth_token' => $access_token ,
                    'oauth_consumer_key' => $api_key ,
                    'oauth_signature_method' => 'HMAC-SHA1' ,
                    'oauth_timestamp' => time() ,
                    'oauth_nonce' => microtime() ,
                    'oauth_version' => '1.0' ,
                ) ;

                // パラメータAとパラメータBを合成してパラメータCを作る
                $params_c = array_merge( $params_a , $params_b ) ;

                // 連想配列をアルファベット順に並び替える
                ksort( $params_c ) ;

                // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
                $request_params = http_build_query( $params_c , '' , '&' ) ;

                // 一部の文字列をフォロー
                $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

                // 変換した文字列をURLエンコードする
                $request_params = rawurlencode( $request_params ) ;

                // リクエストメソッドをURLエンコードする
                // ここでは、URL末尾の[?]以下は付けないこと
                $encoded_request_method = rawurlencode( $request_method ) ;
            
                // リクエストURLをURLエンコードする
                $encoded_request_url = rawurlencode( $request_url ) ;
            
                // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
                $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

                // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
                $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

                // base64エンコードして、署名[$signature]が完成する
                $signature = base64_encode( $hash ) ;

                // パラメータの連想配列、[$params]に、作成した署名を加える
                $params_c['oauth_signature'] = $signature ;

                // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
                $header_params = http_build_query( $params_c , '' , ',' ) ;

                // リクエスト用のコンテキスト
                $context = array(
                    'http' => array(
                        'method' => $request_method , // リクエストメソッド
                        'header' => array(			  // ヘッダー
                            'Authorization: OAuth ' . $header_params ,
                        ) ,
                    ) ,
                ) ;

                // パラメータがある場合、URLの末尾に追加
                if( $params_a ) {
                    $request_url .= '?' . http_build_query( $params_a ) ;
                }

                // オプションがある場合、コンテキストにPOSTフィールドを作成する (GETの場合は不要)
            //	if( $params_a ) {
            //		$context['http']['content'] = http_build_query( $params_a ) ;
            //	}

                // cURLを使ってリクエスト
                $curl = curl_init() ;
                curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
                curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
                curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
                curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
                curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
                curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
            //	if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {		// GETの場合は不要
            //		curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
            //	}
                curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
                $res1 = curl_exec( $curl ) ;
                $res2 = curl_getinfo( $curl ) ;
                curl_close( $curl ) ;

                // 取得したデータ
                $json = substr( $res1, $res2['header_size'] ) ;		// 取得したデータ(JSONなど)
                $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

                // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
                // $json = file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

                // JSONをオブジェクトに変換
                $obj = json_decode( $json ) ;
                $obj_header = $this->header_decode($header);
                Log::debug('$obj_header:'.print_r($obj_header,true));            
                Log::debug('$obj:'.print_r($obj,true));         

                //非公開ユーザーの場合、usersがないのでここには入らない
                if(!empty($obj->users)){
                    $obj_collection->append($obj->users);
                    Log::debug('x-rate-limit-remaining:'.print_r($obj_header['x-rate-limit-remaining'],true));
                    if($obj_header['x-rate-limit-remaining'] < 1) {
                        Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                        
                        //リクエスト上限に引っかかった
                        return array(
                            'res' => 'LIMIT',
                            'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                            'rst' => $obj_collection
                        );
    
                    }
                }else{
                    //'x-rate-limit-remaining'リクエストできる残回数
                    if($obj_header['x-rate-limit-remaining'] < 1) {
                        Log::debug('フォロワー取得上限です！自動フォローを一時中断します');                        
                        //リクエスト上限に引っかかった
                        return array(
                            'res' => 'LIMIT',
                            'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                            'rst' => $obj_collection
                        );
    
                    }
                    //ここに入るのはアカウントが非公開であることを想定
                    //非公開の場合はこのユーザーをスキップする
                    return array(
                        'res' => 'PRIVATE',
                        'msg' => '非公開のユーザーです。このユーザーはスキップします',
                        'rst' => false
                    );
                }
                
            } while( $_SESSION["next_cursor"] = strval($obj->next_cursor_str) );

            Log::debug('全てのフォロワー取得に成功しました');   
            Log::debug('$obj_header:'.print_r($obj_header,true));            
            Log::debug('$obj:'.print_r($obj,true));            
            if(!empty($obj->users)){                    
                Log::debug('$obj フォロワーを取得しました:'.print_r($obj,true));
                return array(
                    'res' => 'OK',
                    'msg' => 'フォロワーを取得しました',
                    'rst' => $obj_collection
                );
            }else{
                Log::debug('$obj フォロワーの取得に失敗しました=>:'.print_r($obj,true));
                return array(
                    'res' => 'NG',
                    'msg' => 'フォロワー取得に失敗しました',
                    'rst' => $obj_collection
                );
            }        
        }

    }

    /**
     * $usernameをフォローする
     * 
     * @param httpレスポンスヘッダをオブジェクトに変換する
     * @return　変換済配列（ただし、[x-rate-limit-remaining]と[x-rate-limit-reset]を取得する）
    **/
    public function header_decode($header)
    {
        //改行で分割する
        $headerArr = explode("\n", $header);        
        //余計な文字を削除
        $headerArr = array_map('trim', $headerArr);

        $header_decoded = array();

        foreach($headerArr as $headerRow){
            $headerRowData = explode(":", $headerRow);

            if(is_array($headerRowData) && count($headerRowData)==2){
                if($headerRowData[0]=="x-rate-limit-remaining"){
                    //echo "x-rate-limit-remaining --- ".$headerRowData[1]."<BR>";
                if(is_numeric($headerRowData[1])){
                    $header_decoded[$headerRowData[0]] = intval($headerRowData[1],10);
                    }
                }else if($headerRowData[0]=="x-rate-limit-reset"){
                    //echo "x-rate-limit-reset --- ".$headerRowData[1]."<BR>";
                    if(is_numeric($headerRowData[1])){
                        $header_decoded[$headerRowData[0]] = intval($headerRowData[1],10);
                    }
                }
            }
        }
        return $header_decoded;

    }

    /**
     * typeに属するscreen_nameを配列の形で取得する
     * 
     * @param $account_id:アカウントID　
     * @param $type (0:ターゲットアカウント 1:フォロー済アカウント　2:アンフォローアカウント)
     * @return　array
    **/
    public function getUseraccountArray($account_id, $type)
    {
        $screenName_array=array();
        
        $useraccount_obj = Db::get_useraccount($account_id, $type);
        foreach($useraccount_obj as $key => $val){
            $screenName_array[] = $val['screen_name'];
        }

        Log::debug('$screenName_array:'.print_r($screenName_array,true));
        return $screenName_array;
    }

    /**
     * $usernameをフォローする
     * 
     * @param $username：@のあとのアカウントID
     * @return　フォローできた：$json / フォローできなかった：false
     * $json = array(
     *    'id' => $obj->id_str,
     *    'name' => $obj->user->screen_name,
     *    'created_at' => $obj->created_at,
     *    'text' => $obj->text
     * );
     * 
    **/
    public function doFollow($username)
    {
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';        
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$acrive_user:'.print_r($screen_name,true));
        if(isset($u_id) && isset($screen_name)){

            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            // 設定
            $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n';	// APIキー
            $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA';	// APIシークレット
            $access_token = $u_info[0]['access_token'];	// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];	// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/friendships/create.json' ;	// エンドポイント
            $request_method = 'POST' ;

            // パラメータA (オプション)
            $params_a = array(
        //      "user_id" => "1528352858",
        		"screen_name" => $username,
        //		"follow" => "false",
            ) ;

            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

            // パラメータB (署名の材料用)
            $params_b = array(
                'oauth_token' => $access_token ,
                'oauth_consumer_key' => $api_key ,
                'oauth_signature_method' => 'HMAC-SHA1' ,
                'oauth_timestamp' => time() ,
                'oauth_nonce' => microtime() ,
                'oauth_version' => '1.0' ,
            ) ;

            // パラメータAとパラメータBを合成してパラメータCを作る
            $params_c = array_merge( $params_a , $params_b ) ;

            // 連想配列をアルファベット順に並び替える
            ksort( $params_c ) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params_c , '' , '&' ) ;

            // 一部の文字列をフォロー
            $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;

            // リクエストメソッドをURLエンコードする
            // ここでは、URL末尾の[?]以下は付けないこと
            $encoded_request_method = rawurlencode( $request_method ) ;
        
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
        
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params_c['oauth_signature'] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params_c , '' , ',' ) ;

            // リクエスト用のコンテキスト
            $context = array(
                'http' => array(
                    'method' => $request_method , // リクエストメソッド
                    'header' => array(	// ヘッダー
                        'Authorization: OAuth ' . $header_params ,
                    ) ,
                ) ,
            ) ;

            // パラメータがある場合、URLの末尾に追加 (POSTの場合は不要)
        //	if ( $params_a ) {
        //		$request_url .= '?' . http_build_query( $params_a ) ;
        //	}

            // オプションがある場合、コンテキストにPOSTフィールドを作成する
            if ( $params_a ) {
                $context['http']['content'] = http_build_query( $params_a ) ;
            }

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
            curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
            if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {
                curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
            }
            curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $json = substr( $res1, $res2['header_size'] ) ;	// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // JSONをオブジェクトに変換 (処理する場合)
            $obj = json_decode( $json ) ;

            Log::debug('$obj@dofollw:'.print_r($obj,true));
            Log::debug('$header@dofollw:'.print_r($header,true));
            if(empty($obj->errors)){ 

                //フォロー済リストとしてDBに追加する                
                $word_id = uniqid();//一意のIDを発行する
                $type = 1;//0:ターゲットアカウント 1:フォロー済アカウント 2:アンフォローアカウント
                $id = $u_info[0]['id'];
                
                try{
                    $id = $u_info[0]['id'];

                    $data = array();
                    $data['id'] = $word_id;
                    $data['account_id'] = $id;
                    $data['screen_name'] = $username;
                    $data['type'] = $type; //0:フォロワーサーチ 1:いいねキーワード
                    $data['delete_flg'] = 0;
                    $data['created_at'] = date('Y:m:d h:i:s');

                    $post = Model_Useraccount::forge();
                    $post->set($data);
                    $rst = $post->save();//ユーザーアカウントをDBに保存する

                    if($rst){
                        Log::debug('フォロー済リストに追加しました！');
                        return array(
                            'res' => 'OK',
                            'msg' => 'フォローに成功しました',
                            'rst' => array(
                                'id' => $obj->id_str,
                                'name' => $obj->screen_name,
                                'text' => $obj->description,
                                'created_at' => $data['created_at']
                            )
                        );
                    }else{
                        Log::debug('フォロー済リストに追加できませんでした。。');
                        return array(
                            'res' => 'NG',
                            'msg' => 'フォローに失敗しました',
                            'rst' => array(
                                'id' => $obj->id_str,
                                'name' => $obj->screen_name,
                                'text' => $obj->description,
                                'created_at' => $data['created_at']
                            )
                        );
                    }


                }catch(Exception $e) {
                    return array(
                        'res' => 'NG',
                        'msg' => $e->getMessage(),
                        'rst' => null
                    );
                }            

            }else{
                if($obj->errors[0]->code===161){
                    //これ以上フォローできない状態
                    //３時間以上あけないと解除されないみたいなのでメッセージをそれ用に変える
                    return array(
                        'res' => 'FOLLOWLIMIT',
                        'msg' => 'これ以上フォローすることができません。３時間以上時間をおいてフォローを再開してください',
                        'rst' => $obj
                    );
                }else{
                    Log::debug('$obj フォローに失敗=>:'.print_r($obj,true));
                    return array(
                        'res' => 'NG',
                        'msg' => 'フォローに失敗しました',
                        'rst' => $obj
                    );
                }
            }   

        }

    }


    /**
     * $usernameをフォローする
     * 
     * @param ユーザーごとの、残り使用可能回数を取得
     * @return　json
    **/
    public function get_rate_limit_status()
    {
        $u_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
        $screen_name = !empty($_SESSION['active_user']) ? $_SESSION['active_user'] : '';        
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$screen_name:'.print_r($screen_name,true));
        if(isset($u_id) && isset($screen_name)){
            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            // 設定
            $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n' ;		// APIキー
            $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA' ;		// APIシークレット
            $access_token = $u_info[0]['access_token'];		// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];		// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/application/rate_limit_status.json' ;		// エンドポイント
            $request_method = 'GET' ;

            // パラメータA (オプション)
            $params_a = array(
        //		"resources" => "statuses,users",
            ) ;

            // キーを作成する (URLエンコードする)
            $signature_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret ) ;

            // パラメータB (署名の材料用)
            $params_b = array(
                'oauth_token' => $access_token ,
                'oauth_consumer_key' => $api_key ,
                'oauth_signature_method' => 'HMAC-SHA1' ,
                'oauth_timestamp' => time() ,
                'oauth_nonce' => microtime() ,
                'oauth_version' => '1.0' ,
            ) ;

            // パラメータAとパラメータBを合成してパラメータCを作る
            $params_c = array_merge( $params_a , $params_b ) ;

            // 連想配列をアルファベット順に並び替える
            ksort( $params_c ) ;

            // パラメータの連想配列を[キー=値&キー=値...]の文字列に変換する
            $request_params = http_build_query( $params_c , '' , '&' ) ;

            // 一部の文字列をフォロー
            $request_params = str_replace( array( '+' , '%7E' ) , array( '%20' , '~' ) , $request_params ) ;

            // 変換した文字列をURLエンコードする
            $request_params = rawurlencode( $request_params ) ;

            // リクエストメソッドをURLエンコードする
            // ここでは、URL末尾の[?]以下は付けないこと
            $encoded_request_method = rawurlencode( $request_method ) ;
        
            // リクエストURLをURLエンコードする
            $encoded_request_url = rawurlencode( $request_url ) ;
        
            // リクエストメソッド、リクエストURL、パラメータを[&]で繋ぐ
            $signature_data = $encoded_request_method . '&' . $encoded_request_url . '&' . $request_params ;

            // キー[$signature_key]とデータ[$signature_data]を利用して、HMAC-SHA1方式のハッシュ値に変換する
            $hash = hash_hmac( 'sha1' , $signature_data , $signature_key , TRUE ) ;

            // base64エンコードして、署名[$signature]が完成する
            $signature = base64_encode( $hash ) ;

            // パラメータの連想配列、[$params]に、作成した署名を加える
            $params_c['oauth_signature'] = $signature ;

            // パラメータの連想配列を[キー=値,キー=値,...]の文字列に変換する
            $header_params = http_build_query( $params_c , '' , ',' ) ;

            // リクエスト用のコンテキスト
            $context = array(
                'http' => array(
                    'method' => $request_method , // リクエストメソッド
                    'header' => array(			  // ヘッダー
                        'Authorization: OAuth ' . $header_params ,
                    ) ,
                ) ,
            ) ;

            // パラメータがある場合、URLの末尾に追加
            if( $params_a ) {
                $request_url .= '?' . http_build_query( $params_a ) ;
            }

            // オプションがある場合、コンテキストにPOSTフィールドを作成する (GETの場合は不要)
        //	if( $params_a ) {
        //		$context['http']['content'] = http_build_query( $params_a ) ;
        //	}

            // cURLを使ってリクエスト
            $curl = curl_init() ;
            curl_setopt( $curl, CURLOPT_URL , $request_url ) ;
            curl_setopt( $curl, CURLOPT_HEADER, 1 ) ; 
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST , $context['http']['method'] ) ;	// メソッド
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER , false ) ;	// 証明書の検証を行わない
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER , true ) ;	// curl_execの結果を文字列で返す
            curl_setopt( $curl, CURLOPT_HTTPHEADER , $context['http']['header'] ) ;	// ヘッダー
        //	if( isset( $context['http']['content'] ) && !empty( $context['http']['content'] ) ) {		// GETの場合は不要
        //		curl_setopt( $curl , CURLOPT_POSTFIELDS , $context['http']['content'] ) ;	// リクエストボディ
        //	}
            curl_setopt( $curl , CURLOPT_TIMEOUT , 5 ) ;	// タイムアウトの秒数
            $res1 = curl_exec( $curl ) ;
            $res2 = curl_getinfo( $curl ) ;
            curl_close( $curl ) ;

            // 取得したデータ
            $json = substr( $res1, $res2['header_size'] ) ;		// 取得したデータ(JSONなど)
            $header = substr( $res1, 0, $res2['header_size'] ) ;	// レスポンスヘッダー (検証に利用したい場合にどうぞ)

            // [cURL]ではなく、[file_get_contents()]を使うには下記の通りです…
            // $json = file_get_contents( $request_url , false , stream_context_create( $context ) ) ;

            // JSONをオブジェクトに変換
            $obj = json_decode( $json ) ;
            
            Log::debug('rete_limit_status:'.print_r($header,true));
        }

    }


    /**
     * $textにフォロワーターゲットキーワードが含まれるか調べる
     * 
     * @param $text 調査する文字列（プロフィール文）
     * @param $search_keyword フォロワーサーチキーワード（Db::get_keyword($u_id, 0)で取得したオブジェクト）
     * @return　含まれる：true 　含まれない：false
    **/
    public function checkFollowTarget($text, $search_keyword)
    {
        $result_or=true; //ORキーワードで調査した結果
        $or_num=0;//ORキーワードの数
        $result_and=true;//ANDキーワードで調査した結果
        $and_num=0;
        $result_not=false;//NOTキーワードで調査した結果
        Log::debug('$search_keyword:'.print_r($search_keyword,true));
        foreach($search_keyword as $key => $val){
            Log::debug('$text:'.print_r($text,true));
            Log::debug('$search_word:'.print_r($val['word'],true));
            if($val['logic'] === '0'){
                $and_num = $and_num + 1;
                //ANDで登録したキーワード
                //ANDキーワードが含まれていない時点で$result_andをfalse
                if(strpos($text,$val['word']) === false){
                    //含まれていない場合
                    $result_and=false;
                }

            }

            if($val['logic'] === '1'){
                $or_num = $or_num + 1;
                //ORで登録したキーワード
                //ORキーワードのうち一つでも含まれていれば$result_orはtrue
                if(strpos($text,$val['word']) === false){
                    //含まれていない場合
                    $result_or=false;
                }else{
                    //含まれている場合はtrueにする
                    $result_or=true;
                }
            }

            if($val['logic'] === '2'){
                //NOTで登録したキーワード
                //NOTキーワードは含まれている時点で$result_notをfalse
                if(strpos($text,$val['word']) !== false){
                    //含まれている場合
                    $result_not=true;
                }
            }

        }

        //キーワードがない場合はfalseとみなす
        if($and_num===0) $result_and=false;
        if($or_num===0) $result_or=false;

        if(!$result_not){
            if($result_and || $result_or){
                //どっちかの判定がtrue
                return true;
            }else{
                return false;
                //NOTキーワードのみが登録されている場合に、そのワードが含まれていなくてもfalse（フォローしない）となる
            }

        }else{
            //NOTキーワードが含まれている場合はfalse
            return false;
        }

    }
    


}