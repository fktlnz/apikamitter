<?php


use \Model\Db; //Dbモデルをインポート

 class MAILTYPE {
    const FINISH_AUTOFOLLOW             = 0; // 自動フォロー完了メール
    const FINISH_AUTOLIKE               = 1; // 自動いいね完了メール
    const FINISH_AUTOUNFOLLOW           = 2; // 自動アンフォロー完了メール
    const ERROR_REQUEST_AUTOFOLLOW      = 3; // リクエスト上限（自動フォロー）
    const ERROR_REQUEST_AUTOLIKE        = 4; // リクエスト上限（自動いいね）
    const ERROR_REQUEST_AUTOUNFOLLOW    = 5; // リクエスト上限（自動アンフォロー）
    const ERROR_SPAM_DETECTED           = 6; // スパム検知メール
}


class Controller_Api extends Controller_Rest
{
    protected $format = 'json';

//========================フロントから下記apiにリクエストが来る=========================//
    /**
     * ログイン済ユーザーかチェックする
     * 
     * @param none
     * @return array('res' => 'OK or NG' , 'error' => array(NGの場合にエラーメッセージを格納します))
    **/
    public function get_checkLogin()
    {
        Log::debug('ログインしているユーザーですか？');

        //ログインしていなければログインに飛ばす
        if(!Auth::check()){
            Log::debug('ログインしていません。ログイン画面に飛ばします');
            return $this->response(array(
                'res' => 'NOTLOGIN',
                'msg' => 'ログインしてください',
                'rst' => null
            ));            
        }else{
            Log::debug('ログインユーザーです＾＾');
            return $this->response(array(
                'res' => 'OK',
                'msg' => 'ログイン済です',
                'rst' => null
            ));            
        }
    }

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
                        if(!Session::get('user_id')){
                            Log::debug('セッションをスタートします！！１');
                            session_start();        
                        }
                        session_regenerate_id( true );
                        // $_SESSION["user_id"] = Auth::get('id');
                        Session::set('user_id', Auth::get('id'));

                        Session::set("unfollow_type", true);//アンフォローするとき、非アクティブユーザーのフォロー解除するか、フォローバックしていないユーザーをフォロー解除するか
                        Session::set("mail_status", '0');//デフォルトはメール配信しない、とする

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
     * （パスワードリマインダー）認証キーを送信する
     * 
     * @param none
     * @return array('res' => 'OK or NG' , 'error' => array(NGの場合にエラーメッセージを格納します))
    **/
    public function post_passremindsend()
    {
        Log::debug('（パスワードリマインダー）認証キーを送信します->'.print_r(Input::post("email"), true));
        $email = Input::post("email");
        $rst_email = Db::chk_emailExist($email);
        Log::debug('email存在確認結果->'.print_r($rst_email['count'], true));

        $username = Input::post("username");
        $rst_username = Db::chk_usernameExist($username);
        Log::debug('username存在確認結果->'.print_r($rst_username['count'], true));        

        if($rst_email['count'] && $rst_username['count']){

            //セッションにユーザー名を格納しておく
            Session::set('username', $username);

            $auth_key = $this->makeRandKey(); //認証キー生成
 
            // メール情報
            $mailto = $email; // 宛先のメールアドレス
            $subject = "【パスワード再発行認証】｜神ったー";
            $mailfrom = "From:webukatsutest@service-1.masashisite.com"; // From:送信元のメールアドレス(サーバパネルで設定したやつ)
            $content = <<<EOT
本メールアドレス宛にパスワード再発行のご依頼がありました。
下記のURLにて認証キーをご入力頂くとパスワードが再発行されます。

認証キー：{$auth_key}
※認証キーの有効期限は30分となります


////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
        
            // 文字化けするようなら下記のコメントアウト解除してみて
            // mb_language("ja");
            mb_internal_encoding("UTF-8");
            
            // メール送信処理
            $result = mb_send_mail($mailto,$subject,$content,$mailfrom);

            //結果をフロントに返す
            if($result){
                Log::debug('認証コードのメール送信完了');

                //認証に必要な情報をセッションへ保存
                Session::set('auth_key', $auth_key);
                Session::set('auth_email', $mailto);
                Session::set('auth_key_limit', time()+(60*30));//認証コードの有効時間を30分とする
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => '送信完了しました。認証キーを確認してください。',
                    'rst' => true
                ));            
            }else{
                Log::debug('認証コードのメール送信に失敗しました');
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => '送信に失敗しました。サーバー管理者に問い合わせてください',
                    'rst' => false
                ));            
            }

        }else{

            Log::debug('登録が確認できませんでした');
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => '登録が確認できませんでした。',
                    'rst' => false
                ));   

        }

        
    }

    /**
     * （パスワードリマインダー）認証キーを確認して、新しいパスワードを発行する
     * 
     * @param none
     * @return array('res' => 'OK or NG' , 'error' => array(NGの場合にエラーメッセージを格納します))
    **/
    public function post_passremindrecieve()
    {
        Log::debug('（パスワードリマインダー）認証キーを確認して、新しいパスワードを発行します->'.print_r(Input::post("code"), true));

        //入力されたコードを確認
        $auth_key_input = Input::post('code');
        if($auth_key_input !== Session::get('auth_key')){
            Log::debug('誤った認証コードです');
            return $this->response(array(
                'res' => 'NG',
                'msg' => '誤った認証コードです',
                'rst' => false
            ));  
        }

        //認証コードの有効時間を確認
        if(time() > Session::get('auth_key_limit')){
            Log::debug('認証コードの有効期限が切れています');
            return $this->response(array(
                'res' => 'NG',
                'msg' => '認証コードの有効期限が切れています',
                'rst' => false
            ));
        }

        //usersテーブルのパスワードをupdateする
        $username = Session::get('username');
        $update_pass = Auth::reset_password($username);
        Log::debug('新しいパスワードを発行しました->'.print_r($update_pass, true));
        
        // メール情報
        $mailto = Session::get('auth_email'); // 宛先のメールアドレス
        Log::debug('$mailto'.print_r($mailto, true));
        $subject = "【パスワード再発行完了】｜神ったー";
        $mailfrom = "From:webukatsutest@service-1.masashisite.com"; // From:送信元のメールアドレス(サーバパネルで設定したやつ)
        $content = <<<EOT
本メールアドレス宛にパスワードの再発行を致しました。
下記のURLにて再発行パスワードをご入力頂き、ログインください。

再発行パスワード：{$update_pass}

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
        
        // 文字化けするようなら下記のコメントアウト解除してみて
        // mb_language("ja");
        mb_internal_encoding("UTF-8");
        
        // メール送信処理
        $result = mb_send_mail($mailto,$subject,$content,$mailfrom);

        //結果をフロントに返す
        if($result){
            Log::debug('新パスワードのメール送信完了');
            //セッションを削除する
            Session::delete('username');
            Session::delete('auth_key');
            Session::delete('auth_email');
            Session::delete('auth_key_limit');
            return $this->response(array(
                'res' => 'OK',
                'msg' => '発行完了しました。ログインしてください。',
                'rst' => true
            ));
        }else{
            Log::debug('新パスワードのメール送信に失敗しました');
            return $this->response(array(
                'res' => 'NG',
                'msg' => '送信に失敗しました。サーバー管理者に問い合わせてください',
                'rst' => false
            ));
        }
    }

    /**
     * Titterアカウントの認証処理をする
     * ⇒AccessTokenおよびAccessTokenSecretを取得して、DBに格納する
     * 
     * @param none
     * @return $json
    **/
    public function makeRandKey()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJLKMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < 15; ++$i) {
            $str .= $chars[mt_rand(0, 61)];
        }
        return $str;
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！２');
            session_start();        
        }       
        //設定項目
        $api_key = "PL2EEcGoYzjCRcfY8TA48wE1n"; //API Key
        $api_secret="o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA"; //API Secret
        $callback_url="http://service-1.masashisite.com/#/home"; //Callback URL 

        //レスポンスする連想配列
        $json = array(
            'res' => 'NG',
            'msg' => '',
            'screen_name' => ''
        );
        Log::debug('get_certifyには来てる');

        //認証しているアカウントの数を取得
        //10人超えている場合はNG
        // $u_id = $_SESSION["user_id"];
        $u_id = Session::get('user_id');

        $rst_myaccountNum = Db::get_myaccountnum($u_id);
        Log::debug('認証アカウント数->'.print_r($rst_myaccountNum['count'], true));
        if($rst_myaccountNum['count'] > 10) {
            $json['msg']='認証できるのは10個までです';
            return $this->response($json);
        }
        
        if(isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])){
            Log::debug('oauth_token:'.$_GET['oauth_token']);
            Log::debug('oauth_verifier:'.$_GET['oauth_verifier']);
            //認証画面で承認した場合
            //アクセストーンを取得するための処理
            /*** [手順5] [手順5] アクセストークンを取得する ***/

            //[リクエストトークン・シークレット]をセッションから呼び出す
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
                $data['user_id'] = Session::get('user_id');;
                // $data['user_id'] = $_SESSION['user_id'];
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
            // session_start() ;
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');;
        // $u_id = $_SESSION["user_id"];
        
        Log::debug('Userid:'.print_r($u_id,true));
        $screen_name = Db::get_screenName($u_id);
        if(count($screen_name) > 0){
            //認証済みアカウントが存在する場合
            if(Session::get('active_user') === null){
                //初回アクセス時に入る。取得したscreen_nameの最初のアカウントをactive_userとする
                Session::set('active_user', $screen_name[count($screen_name)-1]['screen_name']);
                // $_SESSION['active_user'] = $screen_name[count($screen_name)-1]['screen_name'];
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
        // global $IsLogin;
        // Log::debug('$IsLogIn:'.print_r($IsLogin,true));
        // if(!$IsLogin){
        //      return $this->response(array(
        //             'res' => 'NOTLOGIN',
        //             'msg' => 'ログインしてください',
        //             'rst' => null               
        //         ));
        // }
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $username = Session::get('active_user');;
        // $username = $_SESSION['active_user'];
        $twitter_profile = $this->getTwitterProfile($username);        

        Log::debug('twitter_profile:'.print_r($twitter_profile['rst'],true));
        
        if($twitter_profile !== null){

            if($twitter_profile['res']==='OK'){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => $twitter_profile['msg'],
                    'rst' => $twitter_profile['rst']                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $twitter_profile['msg'],
                    'rst' => $twitter_profile['rst']               
                ));
                
            }

        }else{
            //APIでのアクセスに失敗した場合（null）

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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');;
        // $u_id = $_SESSION["user_id"];
        $screen_name = Input::get('screen_name');

        if($u_id !== null && $screen_name !== null){

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
     * 対象のTwitterアカウント(myaccountテーブル)の情報を取得してフロントに返す
     * 
     * @param none
     * @return　json
    **/
    public function get_getuserinfo()
    {
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        // $u_id = $_SESSION["user_id"];
        $screen_name = Input::get('screen_name');
        Log::debug('$u_id:'.$u_id);
        Log::debug('screen_name:'.$screen_name);

        //アカウントを切り替えたか
        //切り替えた場合はセッションをリセットする
        $IsChangeUser = ($screen_name === Session::get('active_user'));
        // $IsChangeUser = ($screen_name === $_SESSION['active_user']);
        Log::debug('IsChangeUser'.$IsChangeUser);
        //===アクティブユーザーを変更する===///
        Session::set('active_user',$screen_name);
        // $_SESSION['active_user'] = $screen_name;
        Log::debug('アクティブユーザー変更：'.Session::get('active_user'));
        

        //アクティブユーザーを切り替えたときに
        //前のアカウントで保持したセッションをリセットする
        if(!$IsChangeUser){            
            Log::debug('アクティブアカウントが切り替わったため、セッションを削除します');
            Session::delete("json_collection_liked_list");            
            Session::delete("skip_num");
            Session::delete("skip_num_unf");
            Session::delete("UnFollowPotentialList");
            Session::delete('unfollow-type');
            Session::delete('follower_list');
            Session::delete('follower_list_skip_num');
            if(!empty($_SESSION["next_cursor"])){
                unset($_SESSION["next_cursor"]);
            }
            if(!empty($_SESSION["next_cursor_unf"])){
                unset($_SESSION["next_cursor_unf"]);
            }
        }

        if($u_id !== null && $screen_name !== null){

            Log::debug('Userid@getuserinfo:'.print_r($u_id,true));
            $rst = $this->getUserInfo($u_id, $screen_name);
            if($rst){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'アカウントを切り替えました。',
                    'active_user' => Session::get('active_user'),
                    'result' => $rst,                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アカウントの切り替えに失敗しました。ネットワークを確認してください。',
                    'active_user' => Session::get('active_user'),
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'アクティブユーザーの取得に成功しました',
                    'active_user' => Session::get('active_user')
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
        $text = Input::get('text');
        $s_id = Input::get('id');
        Log::debug('text:'.print_r($text,true));
        if($text !== null && $s_id !== null){            
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $id = Input::get('id');
        $text = Input::get('text');
        $time = Input::get('time');
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('id:'.print_r($id,true));
        Log::debug('text:'.print_r($text,true));
        Log::debug('time:'.print_r($time,true));
        Log::debug('u_id:'.print_r($u_id,true));
        Log::debug('screen_name:'.print_r($screen_name,true));
        if($id !== null && $text !== null && $time !== null && $u_id !== null && $screen_name !== null){   
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('予約ツイート取得:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){   
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
                        'res' => 'NG',
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
     * キーワードをDBから論理削除する
     * 
     * @param none
     * @return　json
    **/
    public function get_deletetweetschedule()
    {

        $word_id = Input::get('word_id');
        if(isset($word_id)){

            $rst = Db::delete_tweetschedule($word_id);
            Log::debug('delete_accountの結果:'.print_r($rst,true));
            if($rst){
    
                return $this->response(array(
                    'res' => 'OK',
                    'msg' => 'ツイートスケジュールを削除しました',
                    'result' => $rst,                
                ));
    
            }else {
    
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'ツイートスケジュールが削除できませんでした。ネットワークを確認してください。',
                    'result' => $rst,                
                ));
                
            }
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'ツイートスケジュールの削除に失敗しました。時間をおいて再度試してください',
                    'result' => $rst,                
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $json_collection_liked_list = Session::get('json_collection_liked_list');
        //Log::debug('json_collection_liked_list:'.print_r($json_collection_liked_list,true));
        if($json_collection_liked_list !== null) {
            return $this->response(array(
                        'res' => 'OK',
                        'msg' => 'いいね済のリストを取得しました',
                        'rst' => $json_collection_liked_list
                    ));
        }else if($json_collection_liked_list === null){
            return $this->response(array(
                        'res' => 'OK',
                        'msg' => '',
                        'rst' => array()
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
     * データベースからフォロー済リストを取得する
     * 
     * @param none
     * @return　json
    **/
    public function get_getfollowedlist()
    {        
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');

        if($u_id !== null && $screen_name !== null){   
            try{                
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $account_id = $u_info[0]['id'];

                //DBからフォロー済アカウントを取得する
                $followedList = Db::get_useraccount($account_id, 1);

                $followedList_collection=array();
                foreach($followedList as $key => $val){
                    $followedList_collection[] = array(
                        'id' => $val['id'],
                        'name' => $val['screen_name'],
                        'text' => $val['text'],
                        'created_at' => $val['created_at'],
                    );
                    if($key>48) break; //取得するフォロー済アカウントは50個までとする
                }

                Log::debug('followedList_collection1:'.print_r($followedList_collection,true));

                return $this->response(array(
                    'res' => 'UPDATED',
                    'msg' => '',
                    'rst' => $followedList_collection
                ));

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
        }else{
            return $this->response(array(
                        'res' => 'NG',
                        'msg' => '',
                        'rst' => false
                    ));
        }

    }

    /**
     * データベースからアンフォロー済リストを取得する
     * 
     * @param none
     * @return　json
    **/
    public function get_getunfollowedlist()
    {        
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');        

        if($u_id !== null && $screen_name !== null){   
            try{                
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $account_id = $u_info[0]['id'];

                //DBからフォロー済アカウントを取得する
                $unfollowedList = Db::get_useraccount($account_id, 2);

                $unfollowedList_collection=array();
                foreach($unfollowedList as $key => $val){
                    $unfollowedList_collection[] = array(
                        'id' => $val['id'],
                        'name' => $val['screen_name'],
                        'text' => $val['text'],
                        'created_at' => $val['created_at'],
                    );
                    if($key>48) break; //取得するフォロー済アカウントは50個までとする
                }

                Log::debug('followedList_collection1:'.print_r($unfollowedList_collection,true));

                return $this->response(array(
                    'res' => 'UPDATED',
                    'msg' => '',
                    'rst' => $unfollowedList_collection
                ));

            }catch(Exception $e) {
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => $e->getMessage(),
                    'rst' => null
                ));
            }
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');  

        if($u_id !== null && $screen_name !== null){   
            try{
                
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $account_id = $u_info[0]['id'];

                //likeキーワードを取得し、登録がない場合はエラー
                $likeWordArray = Db::get_keyword($account_id, 1);
                if(count($likeWordArray) == 0){
                    return $this->response(array(
                        'res' => 'NG',
                        'msg' => 'いいねキーワードを登録してください！',
                        'rst' => false
                    ));
                }
                
                //いいねをつけるツイートIDの一覧を取得する
                $tweetIdList_forLike = $this->getTweetIdList_forLike($account_id);
                Log::debug('count($tweetIdList_forLike):'.print_r(count($tweetIdList_forLike),true));
                if(count($tweetIdList_forLike) > 0){          

                    $rst = $this->likeTweet($tweetIdList_forLike);

                    if($rst !== null){
                        if($rst !== false){

                            //自動いいね完了メール
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::FINISH_AUTOLIKE);
                            }
                            
                            return $this->response(array(
                                'res' => 'OK',
                                'msg' => 'いいねに成功',
                                'rst' => $rst
                            ));

                        }else if($rst === 'SPAM'){
                            //スパム検知
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_SPAM_DETECTED);
                            }
                            
                            return $this->response(array(
                                'res' => 'OK',
                                'msg' => 'いいねに成功',
                                'rst' => $rst
                            ));

                        }else{
                            //いいね対象リストは取得できたが、すでにいいね済などの理由からいいねができなかった場合、など
                            //いいねの対象がなかった
                            return $this->response(array(
                                'res' => 'NG',
                                'msg' => 'いいねの対象が見つかりませんでした',
                                'rst' => false
                            ));
                        }

                    }else{
                        return $this->response(array(
                            'res' => 'NG',
                            'msg' => 'いいね制限あるいはネット環境が悪い可能性があります',
                            'rst' => null
                        ));
                    }

                }else{
                    return $this->response(array(
                        'res' => 'NG',
                        'msg' => 'いいね対象が見つかりませんでした',
                        'rst' => false
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        
        if($u_id !== null && $screen_name !== null){   
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

                //フォロー済アカウントを取得しておく（フォロー済であるアカウント(30日以内にフォロー)をフォロワーターゲットリストから除外するため）
                $AlreadyFollowList = $this->getUseraccountArray($account_id, 1, 30, true);//type 1:フォロー済アカウント　30日以内に限定
                //フォロー解除済アカウントを取得しておく（フォロー解除済であるアカウントをフォロワーターゲットリストから除外するため）
                $AlreadyUnFollowList = $this->getUseraccountArray($account_id, 2);//type 2:フォロー済アカウント　すべて取得
                
                //フォローした結果を格納する変数
                // [{
                //     'id' => $obj->id_str,//アカウントid
                //     'name' => $obj->screen_name, //スクリーンネーム
                //     'created_at' => date('Y:m:d h:i:s'), //フォローした日時
                //     'text' => $obj->description //プロフ内容
                // }];
                $followResult_Collection=array();
                $result = array(
                    'res' => '',
                    'msg' => '',
                    'rst' => null
                );
                $IsFinishedAllAccount = false; //target_accountのループが完了したかどうか
                foreach($target_account as $key => $val){
                    //今何週目のループかを保持する
                    //フォロー再開時に使用する
                    $key_num = $key;
                    if(Session::get('skip_num') !== null){ 
                        Log::debug('前回途中で中断しています　skip_num=>'.print_r(Session::get('skip_num'),true));                       
                        //中断して再開するとき、もとのループまでスキップする
                        if(Session::get('skip_num') > $key) {
                            Log::debug('この回数スキップします＝＞'.print_r($key,true));
                            continue;
                        }
                    }
                    Log::debug('screen_name:'.print_r($val['screen_name'],true));

                    //フォロワーを取得する
                    //前回の途中から再開する場合はスキップする
                    if(Session::get("follower_list") === null){
                        //フォロワーを取得する（↓戻り値）
                        //return $this->response(array(
                        //    'res' => 'OK/NG',
                        //    'msg' => 'メッセージ内容',
                        //    'rst' => $obj or false or 'request_limit'
                        //));
                        $result = $this->getFollower($val['screen_name']);
                        Log::debug('フォロワー取得した結果:'.print_r($result,true));
                    }

                    //フォロワーターゲットリスト
                    $follower_list = array();//['screen_name1','screen_name2','screen_name3',・・・]

                    if($result["res"] ==='OK' || $result["res"] ==='LIMIT' || Session::get("follower_list") !== null){
                        //全フォロワーを正常に取得しおわった　or　リクエスト上限に到達し、取得が途中で終わった or 前回フォローを途中で終わっていた　場合

                        //取得したフォロワーの中から、フォロワーサーチキーワードがプロフに含まれるアカウントを取得する
                        //前回の途中から再開する場合はスキップする
                        if(Session::get("follower_list") === null){
                            foreach($result["rst"] as $key_array => $val_array){
                                foreach($val_array as $key => $val){
                                    //すでにフォロー済である場合は除外する
                                    if(!in_array($val->screen_name, $AlreadyFollowList)){
                                        if(!in_array($val->screen_name, $AlreadyUnFollowList)){
                                            //取得したフォロワーリストを絞る
                                            //フォロワーサーチキーワードがプロフに含まれる場合にフォロー対象にする
                                            $IsFollowTarget = $this->checkFollowTarget($val->description, $search_keyword);
                                            Log::debug('フォロワーチェックした結果:'.print_r($IsFollowTarget,true));
                                            Log::debug('プロフ内容:'.print_r($val->description,true));
                
                                            if($IsFollowTarget){
                                                array_push($follower_list, $val->screen_name);
                                            }
                                        }else{
                                            Log::debug('すでにフォロー済です'.print_r($val->screen_name,true));
                                        }        
                                    }else{
                                        Log::debug('すでにフォロー済です'.print_r($val->screen_name,true));
                                    }
                                }    
                            }
                             
                        }else{
                            Log::debug('前回フォローが途中で終わっています。follower_listをセッションから取得します');
                            $follower_list = Session::get("follower_list");
                        }

                        Log::debug('$follower_list_new:'.print_r($follower_list,true));    
                        //フォローを開始
                        $dfresult = array(
                            'res' => '',
                            'msg' => '',
                            'rst' => null
                        );
                        foreach($follower_list as $key => $val){
                            Log::debug('このアカウントをふぉろーします:'.print_r($val,true));
                            //フォロー再開時に使用する
                            $follower_list_skip_num = $key;
                            if(Session::get('follower_list_skip_num') !== null){ 
                                Log::debug('前回途中で中断しています　skip_num=>'.print_r(Session::get('follower_list_skip_num'),true));                       
                                //中断して再開するとき、もとのループまでスキップする
                                if(Session::get('follower_list_skip_num') > $key) {
                                    Log::debug('この回数スキップします＝＞'.print_r($follower_list_skip_num,true));
                                    continue;
                                }
                            }

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
                            $dfresult = $this->doFollow($val);
                            if($dfresult['res']==='OK'){
                                $followResult_Collection[]=$dfresult['rst'];
                            }else if($dfresult['res']==='FOLLOWLIMIT'){
                                //フォロー制限になった場合はループを抜ける
                                Log::debug('FOLLOWLIMIT!!'); 
                                break;
                            }
                        } 
                        
                        if($dfresult["res"] ==='FOLLOWLIMIT'){
                            //doFollow内でフォロー上限に達した場合は、再開したときに途中から始められるように
                            //1.スキップする回数 2.next_cursor 3.follower_listを保持する
                            //FOLLOWLIMITに入ってくる場合は、follower_listをすべてフォローしきれていないため、次入ってきたときに途中から再開する
                            //フロント側で3時間以上待機して、自動フォローに入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            Session::set("follower_list", $follower_list);
                            Session::set("follower_list_skip_num", $follower_list_skip_num);
                            Session::set('skip_num',$key_num);

                            //メール配信（フォローリミット）
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOFOLLOW);
                            }

                            return $this->response(array(
                               'res' => 'FOLLOWLIMIT',
                               'msg' => 'フォロー制限のため少し時間をおいてフォローを再開します',
                               'rst' => $followResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                            ));                            

                        }else if($dfresult['res']==='SPAM'){

                            Log::debug('!!!!SPAM判定されました!!!'); 
                            //スパム検知メール
                            //アカウントが停止するため、解除するよう促す
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_SPAM_DETECTED);
                            }

                            return $this->response(array(
                                'res' => 'SPAM',
                                'msg' => 'アカウントが一時停止されました。https://twitter.com にログインしてロック解除してください。',
                                'rst' => false
                            ));
                            
                        }else if($result["res"] ==='LIMIT'){
                            //getFollower内でフォロワー取得上限に達した場合は、再開したときに途中から始められるように
                            //1.スキップする回数 2.next_cursorを保持する
                            //フロント側で15分以上待機して、この関数に入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            // $_SESSION['skip_num'] = $key_num;
                            Session::set('skip_num',$key_num);
                            Session::delete("follower_list");
                            Session::delete("follower_list_skip_num");

                            //メール配信（フォロワー取得リミット）
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOFOLLOW);
                            }

                            //リクエスト上限に達した場合
                            return $this->response(array(
                               'res' => 'LIMIT',
                               'msg' => '15分後、フォロー再開します！',
                               'rst' => $followResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                            ));

                        }
                        
                        if($_SESSION["next_cursor"] == 0 ){
                            //1アカウントのフォロワーすべての取得が完了したら
                            //中断から再開するために保持していたセッション変数をリセットする
                            Log::debug('1ターゲットアカウントのフォロワー取得が完了しました');    
                            Log::debug('フォロー再開用セッション変数をリセットします');    
                            //Session::delete("next_cursor");
                            //Session::delete("skip_num");
                            Session::delete("follower_list");
                            Session::delete("follower_list_skip_num");
                            Log::debug('フォロー再開用セッション変数をリセットしました');                             
                        }else{
                            Log::debug('前回取得したフォロワーリストのフォローが完了しました');    
                            Log::debug('フォロー再開用セッション変数をリセットします');    
                            Session::delete("follower_list");
                            Session::delete("follower_list_skip_num");
                            Log::debug('フォロー再開用セッション変数をリセットしました');   
                            return $this->response(array(
                               'res' => 'LIMIT',
                               'msg' => '15分後、フォロー再開します！!',
                               'rst' => $followResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                            ));
                        } 
                        
                        //全てのターゲットアカウントのチェックが完了したか
                        if(count($target_account) <= $key_num+1){
                            $IsFinishedAllAccount=true;
                        }
                            

                    }else if($result["res"] ==='PRIVATE'){
                        //ユーザーが非公開のときスキップする
                        Log::debug('非公開ユーザーのためスキップします'); 
                        continue;
                    }else if($result["res"] === 'SPAM'){
                        Log::debug('!!!!SPAM判定されました!!!'); 

                        //スパム検知メール
                        //アカウントが停止するため、解除するよう促す
                        if(Session::get('mail_status') === '1'){
                            $this->mailTo(MAILTYPE::ERROR_SPAM_DETECTED);
                        }

                        return $this->response(array(
                            'res' => 'SPAM',
                            'msg' => 'アカウントが一時停止されました。https://twitter.com にログインしてロック解除してください。',
                            'rst' => false
                        ));
                    
                    }else{
                        //ループが何周目か保持して、フロントに返す
                        //15分後もういちど入ってくる
                        // $_SESSION['skip_num'] = $key_num;
                        Session::set('skip_num',$key_num);
                        return $this->response(array(
                            'res' => 'NG',
                            'msg' => 'エラーが発生しました。15分後に再開します。',
                            'rst' => false
                        ));
                    }

                    
                    
                    
                }

                Log::debug('$followResult_Collection:'.print_r($followResult_Collection,true));

                if(isset($_SESSION["next_cursor"]) && $_SESSION["next_cursor"]==0 && $IsFinishedAllAccount ){
                    //ターゲットアカウントのフォロワー次ページがない＋アカウントすべてのループが完了している
                    if(!empty($_SESSION["next_cursor"])){
                        unset($_SESSION["next_cursor"]);
                    }
                    Session::delete("skip_num");
                   
                    //自動フォロー完了メール
                    if(Session::get('mail_status') === '1'){
                        $this->mailTo(MAILTYPE::FINISH_AUTOFOLLOW);
                    }

                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => '自動フォロー完了！',
                        'rst' => $followResult_Collection
                    ));
                }else{
                    return $this->response(array(
                        'res' => 'LIMIT',
                        'msg' => '15分後、フォロー再開します！！',
                        'rst' => $followResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');        
        $word_id = Input::post('id');
        $like_word = Input::post('text');
        $option = Input::post('option');
        $type = Input::post('type');
        Log::debug('word_id:'.print_r($word_id,true));
        Log::debug('like_word:'.print_r($like_word,true));
        Log::debug('option:'.print_r($option,true));
        Log::debug('type:'.print_r($type,true));

        if($u_id !== null && $screen_name !== null && $word_id !== null && $like_word !== null && $option !== null && $type !== null){   
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');        
        $type = Input::get('type'); //0:フォロワーサーチ 1:いいねキーワード
        if($u_id !== null && $screen_name !== null && $type !== null){   
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
        if($word_id !== null){

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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');        
        $word_id = Input::post('id');
        $username = Input::post('username');
        $type = Input::post('type');//0:ターゲットアカウント 1:フォロー済アカウント 2:アンフォローアカウント
        Log::debug('word_id:'.print_r($word_id,true));
        Log::debug('username:'.print_r($username,true));
        Log::debug('type:'.print_r($type,true));
        Log::debug('u_id:'.print_r($u_id,true));

        if($u_id !== null && $screen_name !== null && $word_id !== false && $username !== null && $type !== null){   
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');       
        $type = Input::get('type'); //0:フォロワーサーチ 1:いいねキーワード
        if($u_id !== null && $screen_name !== null && $type !== null){   
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
        if($word_id !== null){

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
        if($username !== null){

            $rst = $this->checkUserAccountExist($username);

            if($rst !== null){
                return $this->response(array(
                    'res' => 'OK',//取得成功
                    'msg' => 'ユーザーアカウントのチェックが完了しました',
                    'rst' => $rst,                
                ));
            }else{
                return $this->response(array(
                    'res' => 'NG',
                    'msg' => '非公開アカウントorアクセス制限orネット環境が悪い可能性があります',
                    'rst' => null,                
                ));
            }
    
            
        }else {

            return $this->response(array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラーです。時間をおいて再度お試しください。',
                    'rst' => $rst,                
                ));

        }
    }



    /**
     * 自動アンフォローを開始する
     * 
     * @param none
     * @return　json  [{'ツイートid'=>'', 'screen_name'=>'@のあとのアカウントID', 'created_at'=>'つぶやいた時間', 'text'=> 'ツイート文'}]
    **/
   
    public function get_startautounfollow()
    {   
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');  
        
        if($u_id !== null && $screen_name !== null){   
            try{
                
                //アカウント情報を取得する(idを使う)
                $u_info = Db::get_userInfo($u_id, $screen_name); 
                Log::debug('u_info:'.print_r($u_info,true));
                $account_id = $u_info[0]['id'];

                //アンフォロー候補リスト
                $WillUnfollowList=array();
                $Friendship_rst=array();

                //=====現在フォローしているアカウントを抽出する=======//

                //フォロー済アカウントを取得（フォローから7日以上経過しているアカウントのみを対象とする）
                $AlreadyFollowList_pure_array = $this->getUseraccountArray($account_id, 1, 7, false);//type 1:フォロー済アカウント
                Log::debug('AlreadyFollowList_pure_array:'.print_r($AlreadyFollowList_pure_array,true));


                //=====非アクティブユーザーのフォロー解除とフォローバックしてくれないアカウントの解除は交互に実施する=======//

                //アンフォローした結果を格納する変数
                // [{
                //     'id' => $obj->id_str,//アカウントid
                //     'name' => $obj->screen_name, //スクリーンネーム
                //     'created_at' => date('Y:m:d h:i:s'), //フォローした日時
                //     'text' => $obj->description //プロフ内容
                // }];
                $UnfollowResult_Collection=array();  
                //アンフォローを開始
                $ufresult = array(
                    'res' => '',
                    'msg' => '',
                    'rst' => null
                );              
                $IsActive = array(
                    'res' => '',
                    'msg' => '',
                    'rst' => null
                );

                if(Session::get("unfollow_type")===true || Session::get("unfollow_type")===null){
                    //前回途中で終わっている場合は、前回の結果を格納する
                    if(Session::get('UnFollowPotentialList') !== null){
                        $AlreadyFollowList_pure_array = Session::get('UnFollowPotentialList');
                    }
                    //非アクティブユーザーをアンフォローする                    
                    foreach($AlreadyFollowList_pure_array as $key => $val){
                        $key_num = $key;//何周目かを保持
                        if(Session::get('skip_num_unf') !== null){ 
                            Log::debug('前回途中で中断しています　skip_num=>'.print_r(Session::get('skip_num_unf'),true));                       
                            //中断して再開するとき、もとのループまでスキップする
                            if(Session::get('skip_num_unf') > $key) {
                                Log::debug('この回数スキップします＝＞'.print_r($key,true));
                                continue;
                            }
                        }
                        
                        Log::debug('アクティブユーザーかチェックします:'.print_r($val,true));
                        $IsActive = $this->checkIfActiveuser($val);//アクティブユーザー（15日以内に投稿履歴がある） レートリミット：900/15min
                        if($IsActive["rst"] === false){
                            Log::debug('このアカウントをアンフォローします:'.print_r($val,true));
                            //フォロワーじゃなくて、非アクティブユーザーの場合はアンフォローする
                            Log::debug('doUnFollowに入ります');
                            //アクティブユーザーでなかったらアンフォローを実行
                            $ufresult = $this->doUnFollow($val);
                            if($ufresult['res']==='OK'){
                                $UnfollowResult_Collection[]=$ufresult['rst'];
                                //DBのuseraccountテーブルの該当アカウントのtypeをアンフォローにする
                                Db::change_useraccountType($account_id, $val, 2);
                            }else if($ufresult['res']==='UNFOLLOWLIMIT'){
                                //再開したときに途中から始められるように
                                //1.スキップする回数 2.AlreadyFollowList_pure_arrayを保持する
                                //フロント側で3時間以上待機して、自動フォローに入ってくる
                                Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                                //次回続きから取得できるようにセッションにページ情報を格納しておく
                                Session::set('skip_num_unf',$key_num);
                                Session::set('UnFollowPotentialList',$AlreadyFollowList_pure_array);

                                //自動アンフォロー制限メール
                                if(Session::get('mail_status') === '1'){
                                    $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW);
                                }

                                return $this->response(array(
                                    'res' => 'FOLLOWLIMIT',
                                    'msg' => 'フォロー制限のため少し時間をおいてフォローを再開します',
                                    'rst' => $UnfollowResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                                ));
                            }
                        }else if($IsActive["res"] === "LIMIT" ){
                            Log::debug('このアカウントをアンフォローします:'.print_r($val,true));
                            //再開したときに途中から始められるように
                            //1.スキップする回数 2.AlreadyFollowList_pure_arrayを保持する
                            //フロント側で3時間以上待機して、自動フォローに入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            Session::set('skip_num_unf',$key_num);
                            Session::set('UnFollowPotentialList',$AlreadyFollowList_pure_array);

                            //フォロワーじゃなくて、非アクティブユーザーの場合はアンフォローする
                            Log::debug('doUnFollowに入ります');
                            //アクティブユーザーでなかったらアンフォローを実行
                            $ufresult = $this->doUnFollow($val);
                            if($ufresult['res']==='OK'){
                                $UnfollowResult_Collection[]=$ufresult['rst'];
                                //DBのuseraccountテーブルの該当アカウントのtypeをアンフォローにする
                                Db::change_useraccountType($account_id, $val, 2);
                            }else if($ufresult['res']==='UNFOLLOWLIMIT'){

                                 //自動アンフォロー制限メール
                                if(Session::get('mail_status') === '1'){
                                    $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW);
                                }

                                return $this->response(array(
                                    'res' => 'FOLLOWLIMIT',
                                    'msg' => 'フォロー制限のため少し時間をおいてフォローを再開します',
                                    'rst' => $UnfollowResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                                ));
                            }
                             //自動アンフォロー制限メール
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW);
                            }

                            return $this->response(array(
                                'res' => 'LIMIT',
                                'msg' => '(LIMIT)15分後、アンフォロー再開します！',
                                'rst' => $UnfollowResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                            ));
                                                            

                        }else if($IsActive["rst"] === "SPAM"){
                            //再開したときに途中から始められるように
                            //1.スキップする回数 2.AlreadyFollowList_pure_arrayを保持する
                            //フロント側で3時間以上待機して、自動フォローに入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            Session::set('skip_num_unf',$key_num);
                            Session::set('UnFollowPotentialList',$AlreadyFollowList_pure_array);

                            Log::debug('!!!!SPAM判定されました!!!'); 

                            //自動アンフォロー制限メール
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_SPAM_DETECTED);
                            }

                            return $this->response(array(
                                'res' => 'SPAM',
                                'msg' => 'アカウントが一時停止されました。https://twitter.com にログインしてロック解除してください。',
                                'rst' => false
                            ));

                        }else{    
                            //ページが存在しないなど、予期しないエラーの場合に入ってくる
                            //この場合はこのユーザーはスキップする
                        }
                        
                    }
                    
                    Log::debug('非アクティブユーザーのフォロー解除が完了しました！');
                    Log::debug('UnfollowResult_Collection:'.print_r($UnfollowResult_Collection,true));

                    //セッションをリセット
                    Session::delete("skip_num_unf");
                    Session::delete("UnFollowPotentialList");
                    Session::set("unfollow_type", false);

                    //自動アンフォロー完了メール
                    if(Session::get('mail_status') === '1'){
                        $this->mailTo(MAILTYPE::FINISH_AUTOUNFOLLOW);
                    }

                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => '自動アンフォロー完了！',
                        'rst' => $UnfollowResult_Collection
                    ));
                   
                }else{
                    //フォロー返してくれないユーザーをアンフォローする

                    $Friendship_rst=array(
                        'res' => '',
                        'msg' => null,
                        'rst' => null
                    );
////////////////////４．Friendship_rst["rst"]=$objのなかから、フォローバックしていないアカウントを抽出する(途中から再開する場合はスキップ)
                    if(Session::get('UnFollowPotentialList') === null){
                        //フォロー済アカウントのフレンドシップ状況（フォローバック状況）を確認
                        $Friendship_rst = $this->checkFriendship($AlreadyFollowList_pure_array);
                        //↓$Friendship_rst↓
                        // array(
                        //     'res' => 'OK・NG',
                        //     'msg' => 'メッセージ',
                        //     'rst' => $obj
                        // );
                        // $obj=>（例）
                        // (
                        //     [name] => れいん
                        //     [screen_name] => re1nc
                        //     [id] => 1.0521939843138E+18
                        //     [id_str] => 1052193984313843712
                        //     [connections] => Array
                        //         (
                        //             [0] => following
                        //             [1] => followed_by
                        //         )
                        // )
                        foreach($Friendship_rst["rst"] as $key => $val){                        
                            Log::debug('followed byが含まれるか:'.print_r(in_array("followed_by",$val->connections ),true));
                            if(!in_array("followed_by",$val->connections )){
                                //含まれない場合は、$WillUnfollowListに追加してアンフォロー対象とする
                                $WillUnfollowList[] = $val->screen_name;
                            }
                        }
                    }else{
                        $WillUnfollowList = Session::get('UnFollowPotentialList');
                    }

                    foreach($WillUnfollowList as $key => $val){
                        $key_num = $key;//何周目かを保持
                        if(Session::get('skip_num_unf') !== null){ 
                            Log::debug('前回途中で中断しています　skip_num=>'.print_r(Session::get('skip_num_unf'),true));                       
                            //中断して再開するとき、もとのループまでスキップする
                            if(Session::get('skip_num_unf') > $key) {
                                Log::debug('この回数スキップします＝＞'.print_r($key,true));
                                continue;
                            }
                        }
                        if($Friendship_rst["res"] === 'OK' || Session::get('skip_num_unf') !== null){
                            //checkFriendshipがうまくいったとき or 前回途中で終わっている場合にここに入る
    
                            Log::debug('このアカウントをアンフォローします:'.print_r($val,true));
                            //フォロワーじゃなくて、非アクティブユーザーの場合はアンフォローする
                            Log::debug('doUnFollowに入ります');
                            //アクティブユーザーでなかったらアンフォローを実行
                            $ufresult = $this->doUnFollow($val);
                            if($ufresult['res']==='OK'){
                                $UnfollowResult_Collection[]=$ufresult['rst'];
                                //DBのuseraccountテーブルの該当アカウントのtypeをアンフォローにする
                                Db::change_useraccountType($account_id, $val, 2);
                            }else if($ufresult['res']==='UNFOLLOWLIMIT'){
                                //再開したときに途中から始められるように
                                //1.スキップする回数 2.WillUnfollowListを保持する
                                //フロント側で3時間以上待機して、自動フォローに入ってくる
                                Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                                //次回続きから取得できるようにセッションにページ情報を格納しておく
                                Session::set('skip_num_unf',$key_num);
                                Session::set('UnFollowPotentialList',$WillUnfollowList);

                                //自動アンフォロー完了メール
                                if(Session::get('mail_status') === '1'){
                                    $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW);
                                }

                                return $this->response(array(
                                    'res' => 'FOLLOWLIMIT',
                                    'msg' => 'フォロー制限のため少し時間をおいてフォローを再開します',
                                    'rst' => $UnfollowResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                                ));
                            }
                        }else if($Friendship_rst["res"] === "LIMIT" ){
                            Log::debug('このアカウントをアンフォローします:'.print_r($val,true));
                            //再開したときに途中から始められるように
                            //1.スキップする回数 2.WillUnfollowListを保持する
                            //フロント側で3時間以上待機して、自動フォローに入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            Session::set('skip_num_unf',$key_num);
                            Session::set('UnFollowPotentialList',$WillUnfollowList);
    
                            //フォロワーじゃなくて、非アクティブユーザーの場合はアンフォローする
                            Log::debug('doUnFollowに入ります');
                            //アクティブユーザーでなかったらアンフォローを実行
                            $ufresult = $this->doUnFollow($val);
                            if($ufresult['res']==='OK'){
                                $UnfollowResult_Collection[]=$ufresult['rst'];
                                //DBのuseraccountテーブルの該当アカウントのtypeをアンフォローにする
                                Db::change_useraccountType($account_id, $val, 2);
                            }else if($ufresult['res']==='UNFOLLOWLIMIT'){

                                //自動アンフォロー制限メール
                                if(Session::get('mail_status') === '1'){
                                    $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW);
                                }

                                return $this->response(array(
                                    'res' => 'FOLLOWLIMIT',
                                    'msg' => 'フォロー制限のため少し時間をおいてフォローを再開します',
                                    'rst' => $UnfollowResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                                ));
                            }

                            //自動アンフォロー制限メール
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW);
                            }
    
                            return $this->response(array(
                                'res' => 'LIMIT',
                                'msg' => '(LIMIT)15分後、アンフォロー再開します！',
                                'rst' => $UnfollowResult_Collection //$followResult_Collection_mergedにするとフロントかえってきたときにIDが重複するためとりあえずこっち
                            ));
                                                            
    
                        }else if($Friendship_rst["rst"] === "SPAM"){
                            //再開したときに途中から始められるように
                            //1.スキップする回数 2.WillUnfollowListを保持する
                            //フロント側で3時間以上待機して、自動フォローに入ってくる
                            Log::debug('次再開したとき'.$key_num.'回スキップします。'); 
                            //次回続きから取得できるようにセッションにページ情報を格納しておく
                            Session::set('skip_num_unf',$key_num);
                            Session::set('UnFollowPotentialList',$WillUnfollowList);

                            //スパム判定メール
                            //アカウント停止されるため、解除を促す
                            if(Session::get('mail_status') === '1'){
                                $this->mailTo(MAILTYPE::ERROR_SPAM_DETECTED);
                            }
    
                            Log::debug('!!!!SPAM判定されました!!!'); 
                            return $this->response(array(
                                'res' => 'SPAM',
                                'msg' => 'アカウントが一時停止されました。https://twitter.com にログインしてロック解除してください。',
                                'rst' => false
                            ));
    
                        }else{    
                            //ページが存在しないなど、予期しないエラーの場合に入ってくる
                            //この場合はこのユーザーはスキップする
                        }
                    }



                    //セッションをリセット
                    Session::delete("skip_num_unf");
                    Session::delete("UnFollowPotentialList");
                    Session::set("unfollow_type", true);

                    //自動アンフォロー完了メール
                    if(Session::get('mail_status') === '1'){
                        $this->mailTo(MAILTYPE::FINISH_AUTOUNFOLLOW);
                    }

                    return $this->response(array(
                        'res' => 'OK',
                        'msg' => '自動アンフォロー完了！',
                        'rst' => $UnfollowResult_Collection
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


    /* ================================
    # ログアウト関数
    =================================*/
    /**
     * ログアウトする
     * 
     * @param none
     * @return　true / false
    **/
    public function get_logout()
    {
        //セッション削除
        Session::delete('unfollow-type');
        Session::delete('skip_num_unf');
        Session::delete('UnFollowPotentialList');
        Session::delete('user_id');
        Session::delete('active_user');
        Session::delete('skip_num');        
        Session::delete('json_collection_liked_list');
        Session::delete('follower_list');
        Session::delete('follower_list_skip_num');
        Session::delete('mail_status');
        if(!empty($_SESSION["next_cursor"])){
            unset($_SESSION["next_cursor"]);
        }
        if(!empty($_SESSION["next_cursor_unf"])){
            unset($_SESSION["next_cursor_unf"]);
        }

        // logout        
        $auth = Auth::instance();
        $rst = $auth->logout();
        Log::debug('ログアウトしました'.print_r($rst,true));
        if($rst){
            return $this->response(array(
                    'res' => true,
                    'msg' => 'ログアウトしました',
                ));
        }else{
            return $this->response(array(
                    'res' => false,
                    'msg' => 'ログアウトに失敗しました。ネット環境を確認してください。',
                ));
        }
    }

    /**
     * メール配信状態をセッションに格納する
     * 
     * @param status(true:配信する　false:配信しない)
     * @return　成功:true 　失敗：false
    **/
    public function get_changemailstatus()
    {
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $status = Input::get('status');
        if($status !== null){
            Log::debug('今のメール配信状態＝＞'.print_r(Session::get('mail_status'),true));
            Session::set('mail_status', $status);
            Log::debug('変更後のメール配信状態＝＞'.print_r(Session::get('mail_status'),true));
            return true;
        }else{
            Log::debug('メール配信状態の変更に失敗しました');
            return false;
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');

        if($u_id !== null && $screen_name !== null){

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
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');

        //アカウントを切り替えたか
        //切り替えた場合はセッションをリセットする
        $IsChangeUser = ($screen_name === Session::get('active_user'));
        Log::debug('IsChangeUser'.$IsChangeUser);

        //アクティブユーザーを切り替えたときに
        //前のアカウントで保持したセッションをリセットする
        if(!$IsChangeUser){            
            Log::debug('アクティブアカウントが切り替わったため、セッションを削除します');
            Session::delete("json_collection_liked_list");            
            Session::delete("skip_num");
            Session::delete("skip_num_unf");
            Session::delete("follower_list_skip_num");
            Session::delete("UnFollowPotentialList");
            Session::delete('unfollow-type');
            Session::delete('follower_list');
            Session::delete('follower_list_skip_num');
            if(!empty($_SESSION["next_cursor"])){
                unset($_SESSION["next_cursor"]);
            }
            if(!empty($_SESSION["next_cursor_unf"])){
                unset($_SESSION["next_cursor_unf"]);
            }
        }
        Log::debug('screen_name'.$screen_name);
        Log::debug('u_id'.$u_id);
        if($u_id !== null && $screen_name !== null){

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

            if($obj){
                //$objが取得できた場合                
                if(empty($obj->errors)){
                    //正常に取得できた場合
                    return array(
                        'res' => 'OK',
                        'msg' => 'プロフィールの取得に成功',
                        'rst' => $obj
                    );
                }else{
                    //$obj取得できたがエラーだった場合
                    return array(
                        'res' => 'NG',
                        'msg' => 'プロフィールの取得に失敗',
                        'rst' => false
                    );
                }

            }else{
                //$objが空の場合
                //１．アクセス制限である
                //２．アカウントが非公開
                //３．ネット環境が悪い
                return array(
                    'res' => 'NG',
                    'msg' => 'リクエストに失敗しました',
                    'rst' => null
                );
            }
            
            
        }else {
            return array(
                    'res' => 'NG',
                    'msg' => 'アプリケーションエラー@プロフィール取得',
                    'rst' => false
                );
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
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');

        if($u_id !== null && $screen_name !== null && isset($word) && isset($num)){

            
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
            $empty_array=array();
            if($obj){

                if(empty($obj->errors)){

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

                }else{
                    return $empty_array;

                }

                

            }else{
                //$objが空の場合
                //１．アクセス制限である
                //２．アカウントが非公開
                //３．ネット環境が悪い
                return $empty_array;
            }
            
            
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
        $IsError=false;//objが取得できなかった場合：true 
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');       

        if($u_id !== null && $screen_name !== null){

            
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
                //Log::debug('$obj:'.print_r($obj,true));  

                if($obj){
                    if(empty($obg->error)){
                        if(empty($obj->errors)){
                            $json_rtn['id'] = $obj->id_str;
                            $json_rtn['name'] = $obj->user->screen_name;
                            $json_rtn['created_at'] = $obj->created_at;
                            $json_rtn['text'] = $obj->text;

                            //Log::debug('$json_rtn:'.print_r($json_rtn,true));
                            $json_collection[]=$json_rtn;
        
                        }else{
                            Log::debug('いいね！に失敗しました=>:'.print_r($obj->errors,true));

                            if(!empty($obj->errors[0]->code)){
                                switch($obj->errors[0]->code){    
                                    //SPAMから守るためにアカウントを一時停止した
                                    case 326:
                                        return 'SPAM';
                                        break;
                                }
                            }
                        }
                        
                    }                    

                }else{
                    //$objが空の場合
                    //１．アクセス制限である
                    //２．アカウントが非公開
                    //３．ネット環境が悪い

                    //$json_collectionが空の場合にアクセス制限、ネット環境悪い可能性があるとメッセージする
                    $IsError=true;
                    
                }

            }

        }

        //セッションに格納したidlistを取得する
        //フロント側のHOME画面で画面更新されるとリストが空になるので、セッションで以前の結果を保持しておく
        $json_collection_liked_list=array();
        $json_collection_liked_list = Session::get('json_collection_liked_list');
        Session::delete('json_collection_liked_list');
        //Log::debug('json_collection_liked_list セッションに入ってた=>:'.print_r($json_collection_liked_list,true));
        if($json_collection_liked_list !== null){
            //セッションに入っていた場合
            //Log::debug('$json_collection 追加前=>:'.print_r($json_collection,true));
            $json_collection_liked_list = array_merge($json_collection_liked_list, $json_collection);
            //Log::debug('$json_collection 追加後=>:'.print_r($json_collection_liked_list,true));

            $setRst = Session::set('json_collection_liked_list', $json_collection_liked_list);
            //Log::debug('$setRst:'.print_r($setRst,true));
            //Log::debug('$json_collection:'.print_r($json_collection_liked_list,true));
            return $json_collection_liked_list;

        }else{
            if(!empty($json_collection)){

                $setRst = Session::set('json_collection_liked_list', $json_collection);
                //Log::debug('$setRst:'.print_r($setRst,true));
                //Log::debug('$json_collection:'.print_r($json_collection,true));
                return $json_collection;

            }else{
                if($IsError){
                    //$json_collectionが空の場合は制限に引っかかっている可能性が高い
                    return null;
                }
                return false;
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
        if(!Session::get('user_id')){
            Log::debug('セッションをスタートします！！');
            session_start();        
        }
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');           
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$screen_name:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){

            
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

            if($obj){
                //Log::debug('$obj:'.print_r($obj,true));
                if(empty($obj->errors)){                    
                    Log::debug('$obj アカウントが存在しました:'.print_r($obj,true));
                    return true;
                }else{
                    Log::debug('$obj アカウントが存在しませんでした=>:'.print_r($obj,true));
                    return false;
                }  
            }else{
                //非公開アカウントorアクセス制限orネット環境が悪い可能性があります
                return null;
            }
                  
        }

    }


    /**
     * $usernameのフォロワーを取得する(get_startautofollow内で使用する)
     * 
     * @param $username：フォロワーを取得したいツイッターアカウント
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
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$screen_name:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){

            
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
                    "cursor" => ( isset($_SESSION["next_cursor"]) && $_SESSION["next_cursor"] != 0 ) ? $_SESSION["next_cursor"] : -1,//中断した場合は、$_SESSION['next_cursor']からページ情報を取得する。初期値:-1
                    // "cursor" => "1647003147843880227",//中断した場合は、$_SESSION['next_cursor']からページ情報を取得する。初期値:-1
                    "count" => "100", //いくつにするのが最適か？多すぎるとspam判定される。。
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
                //Log::debug('$obj_header:'.print_r($obj_header,true));            
                //Log::debug('$obj:'.print_r($obj,true));

                if($obj){
                    if(empty($obj->error)){
                        if(empty($obj->errors)){

                            $obj_collection->append($obj->users);
                            Log::debug('x-rate-limit-remaining:'.print_r($obj_header['x-rate-limit-remaining'],true));
                            //アクセス制限にかかった場合はLIMITを返す
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
                            //１．ページが存在しない
                            Log::debug('$obj タイムライン取得に失敗@getfollower');

                            //アクセス制限にかかった場合はLIMITを返す
                            if($obj_header['x-rate-limit-remaining'] < 1) {
                                Log::debug('フォロワー取得上限です！自動フォローを一時中断します');                                
                                //リクエスト上限に引っかかった
                                return array(
                                    'res' => 'LIMIT',
                                    'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                    'rst' => $obj_collection
                                );
                            }
                            if(!empty($obj->errors[0]->code)){
                                switch($obj->errors[0]->code){
                                    //アカウントが非公開の場合
                                    case 179:
                                        return array(
                                            'res' => 'PRIVATE',
                                            'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                            'rst' => false
                                        );
                                        break;
    
                                    //SPAMから守るためにアカウントを一時停止した
                                    case 326:
                                        return array(
                                            'res' => 'SPAM',
                                            'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                            'rst' => false
                                        );
                                        break;
    
                                    default:
                                        return array(
                                            'res' => 'NG',
                                            'msg' => 'なにかしらのエラーが発生しました',
                                            'rst' => false
                                        );
                                        break;
                                }
                            }else{
                                return array(
                                    'res' => 'NG',
                                    'msg' => 'なにかしらのエラーが発生しました',
                                    'rst' => false
                                );
                            }
                            
                        }
                    }else{
                        
                        Log::debug('$obj タイムライン取得に失敗');
                        // Log::debug('$obj タイムライン取得に失敗=>:'.print_r($obj,true));

                        //アクセス制限にかかった場合はLIMITを返す
                        if($obj_header['x-rate-limit-remaining'] < 1) {
                            Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                            
                            //リクエスト上限に引っかかった
                            return array(
                                'res' => 'LIMIT',
                                'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                'rst' => $obj_collection
                            );
                        }

                        switch($obj->error[0]->code){
                            //アカウントが非公開の場合
                            case 179:
                                return array(
                                    'res' => 'PRIVATE',
                                    'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                    'rst' => false
                                );
                                break;

                            //SPAMから守るためにアカウントを一時停止した
                            case 326:
                                return array(
                                    'res' => 'SPAM',
                                    'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                    'rst' => false
                                );
                                break;

                            default:
                                return array(
                                    'res' => 'NG',
                                    'msg' => 'なにかしらのエラーが発生しました',
                                    'rst' => false
                                );
                                break;
                        }

                    }
                }else{
                    //$objが空の場合
                    //１．アクセス制限である                
                    //２．ネット環境が悪い
                    Log::debug('$objが空です！！タイムライン取得に失敗');
                    return array(
                        'res' => 'NG',
                        'msg' => 'なにかしらのエラーが発生しました',
                        'rst' => null
                    );
                }
                
            } while( $_SESSION["next_cursor"] = strval($obj->next_cursor_str) );

            Log::debug('全てのフォロワー取得に成功しました');   
            // Log::debug('$obj_header:'.print_r($obj_header,true));            
            // Log::debug('$obj:'.print_r($obj,true));            
            // if(!empty($obj->users)){                    
                Log::debug('$obj フォロワーを取得しました');
                // Log::debug('$obj フォロワーを取得しました:'.print_r($obj,true));
                return array(
                    'res' => 'OK',
                    'msg' => 'フォロワーを取得しました',
                    'rst' => $obj_collection
                );
            // }else{
            //     Log::debug('$obj フォロワーの取得に失敗しました=>:'.print_r($obj,true));
            //     return array(
            //         'res' => 'NG',
            //         'msg' => 'フォロワー取得に失敗しました',
            //         'rst' => $obj_collection
            //     );
            // }        
        }

    }
    /**
     * $usernameのフォロワーを取得する(get_startautounfollow内で使用する)
     * 
     * @param $username：フォロワーを取得したいツイッターアカウント
     * @return $this->response(array(
     *              'res' => '成功：OK　失敗：NG',
     *              'msg' => 'メッセージ内容',
     *              'rst' => '成功：レスポンスbody　失敗（フォロワー取得上限になった）:'request_limit'　失敗（取得できなかった）:false'
     *           ));
     * 
     * 
    **/
    public function getFollower_unf($username)
    {
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$screen_name:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){

            
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

                isset($_SESSION['next_cursor_unf']) ? Log::debug('isset($_SESSION["next_cursor_unf"])の値:'.print_r($_SESSION['next_cursor_unf'],true)) : Log::debug('next_cursor_unfなし');
                // パラメータA (オプション)
                $params_a = array(
            //      "user_id" => "1528352858",
                    "screen_name" => $username,
                    "cursor" => isset($_SESSION["next_cursor_unf"]) ? $_SESSION["next_cursor_unf"] : -1,//中断した場合は、$_SESSION['next_cursor_unf']からページ情報を取得する。初期値:-1
                    // "cursor" => "1647003147843880227",//中断した場合は、$_SESSION['next_cursor_unf']からページ情報を取得する。初期値:-1
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
                //Log::debug('$obj_header:'.print_r($obj_header,true));            
                //Log::debug('$obj:'.print_r($obj,true));   

                if($obj){
                    if(empty($obj->error)){
                        if(empty($obj->errors)){

                            $obj_collection->append($obj->users);
                            Log::debug('x-rate-limit-remaining:'.print_r($obj_header['x-rate-limit-remaining'],true));
                            //アクセス制限にかかった場合はLIMITを返す
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
                            //１．ページが存在しない
                            Log::debug('$obj タイムライン取得に失敗@getfollower_unf');

                            //アクセス制限にかかった場合はLIMITを返す
                            if($obj_header['x-rate-limit-remaining'] < 1) {
                                Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                                
                                //リクエスト上限に引っかかった
                                return array(
                                    'res' => 'LIMIT',
                                    'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                    'rst' => $obj_collection
                                );
                            }

                            switch($obj->errors[0]->code){
                                //アカウントが非公開の場合
                                case 179:
                                    return array(
                                        'res' => 'PRIVATE',
                                        'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                        'rst' => false
                                    );
                                    break;

                                //SPAMから守るためにアカウントを一時停止した
                                case 326:
                                    return array(
                                        'res' => 'SPAM',
                                        'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                        'rst' => false
                                    );
                                    break;

                                default:
                                    return array(
                                        'res' => 'NG',
                                        'msg' => 'なにかしらのエラーが発生しました',
                                        'rst' => false
                                    );
                                    break;
                            }
                            
                        }
                    }else{

                        //１．アカウントが非公開
                        //２．
                        Log::debug('$obj タイムライン取得に失敗');

                        //アクセス制限にかかった場合はLIMITを返す
                        if($obj_header['x-rate-limit-remaining'] < 1) {
                            Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                            
                            //リクエスト上限に引っかかった
                            return array(
                                'res' => 'LIMIT',
                                'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                'rst' => $obj_collection
                            );
                        }

                        switch($obj->error[0]->code){
                            //アカウントが非公開の場合
                            case 179:
                                return array(
                                    'res' => 'PRIVATE',
                                    'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                    'rst' => false
                                );
                                break;

                            //SPAMから守るためにアカウントを一時停止した
                            case 326:
                                return array(
                                    'res' => 'SPAM',
                                    'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                    'rst' => false
                                );
                                break;

                            default:
                                return array(
                                    'res' => 'NG',
                                    'msg' => 'なにかしらのエラーが発生しました',
                                    'rst' => false
                                );
                                break;
                        }

                    }
                }else{
                    //$objが空の場合
                    //１．アクセス制限である                
                    //２．ネット環境が悪い
                    Log::debug('$objが空です！！タイムライン取得に失敗');
                    return array(
                        'res' => 'NG',
                        'msg' => 'なにかしらのエラーが発生しました',
                        'rst' => null
                    );
                }
                
            } while( $_SESSION["next_cursor_unf"] = strval($obj->next_cursor_str) );

            Log::debug('全てのフォロワー取得に成功しました');   
            // Log::debug('$obj_header:'.print_r($obj_header,true));            
            // Log::debug('$obj:'.print_r($obj,true));            
            // if(!empty($obj->users)){                    
                Log::debug('$obj フォロワーを取得しました');
                return array(
                    'res' => 'OK',
                    'msg' => 'フォロワーを取得しました',
                    'rst' => $obj_collection
                );
            // }else{
            //     Log::debug('$obj フォロワーの取得に失敗しました=>:'.print_r($obj,true));
            //     return array(
            //         'res' => 'NG',
            //         'msg' => 'フォロワー取得に失敗しました',
            //         'rst' => $obj_collection
            //     );
            // }        
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
     * @param $day 何日以内、何日経過　のもののみ対象
     * @param $flag true($day日以内のものを対象にする)　false(登録から$day以上経過しているものを対象にする)
     * @return　array
    **/
    public function getUseraccountArray($account_id, $type, $day=null, $flag=null)
    {
        $screenName_array=array();
        $useraccount_obj=array();
        if($day !== null && $flag !== null){
            $before_day = '-'.$day.' days';
            Log::debug('何日前？：'.$before_day);
            $date_today=new \DateTime();
            Log::debug('今の時間：'.$date_today->format('Y-m-d H:i:s'));
            $date_today_before = $date_today->modify($before_day)->format('Y-m-d H:i:s');
            Log::debug('30日前の時間：'.$date_today_before);
            $useraccount_obj = Db::get_useraccount($account_id, $type, $date_today_before, $flag);
        }else{
            $useraccount_obj = Db::get_useraccount($account_id, $type);
        }        
        
        foreach($useraccount_obj as $key => $val){
            $screenName_array[] = $val['screen_name'];
        }
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
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$acrive_user:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){
            
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

            //Log::debug('$obj@dofollw:'.print_r($obj,true));
            //Log::debug('$header@dofollw:'.print_r($header,true));

            if($obj){

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
                        $data['text'] = $obj->description;
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
                    if(!empty($obj->errors[0]->code)){

                        switch($obj->errors[0]->code){
                            //これ以上フォローできない状態
                            //一度に連続でフォローできるのは30人でそれを超えるとここに入ってくる
                            case 161:
                                return array(
                                    'res' => 'FOLLOWLIMIT',
                                    'msg' => 'これ以上フォローすることができません。３時間以上時間をおいてフォローを再開してください',
                                    'rst' => $obj
                                );
                                break;
                            //アカウントが非公開の場合
                            case 179:
                                return array(
                                    'res' => 'PRIVATE',
                                    'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                    'rst' => null
                                );
                                break;
    
                            //SPAMから守るためにアカウントを一時停止した
                            case 326:
                                return array(
                                    'res' => 'SPAM',
                                    'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                    'rst' => null
                                );
                                break;
    
                            // case 34:
                            //     //Sorry, that page does not exist
                            //     break;
    
                            default:
                                Log::debug('$obj フォローに失敗');
                                // Log::debug('$obj フォローに失敗'.print_r($obj,true));
                                return array(
                                    'res' => 'NG',
                                    'msg' => 'フォローに失敗しました',
                                    'rst' => $obj
                                );
                        }

                    }else {
                        Log::debug('$obj フォローに失敗');
                        // Log::debug('$obj フォローに失敗=>:'.print_r($obj,true));
                        return array(
                            'res' => 'NG',
                            'msg' => 'フォローに失敗しました',
                            'rst' => $obj
                        );
                    }
                }  

            }else{
                //$objが空の場合
                //１．アクセス制限である
                //２．アカウントが非公開
                //３．ネット環境が悪い
                Log::debug('$objが空です！！フォローに失敗');
                // Log::debug('$objが空です！！フォローに失敗 =>:'.print_r($obj,true));
                return array(
                    'res' => 'NG',
                    'msg' => 'フォローに失敗しました',
                    'rst' => $obj
                );
            }
             

        }

    }
    /**
     * $usernameをアンフォローする
     * 
     * @param $username：@のあとのアカウントID
     * @return　アンフォローできた：$json / アンフォローできなかった：false
     * $json = array(
     *    'id' => $obj->id_str,
     *    'name' => $obj->user->screen_name,
     *    'created_at' => $obj->created_at,
     *    'text' => $obj->text
     * );
     * 
    **/
    public function doUnFollow($username)
    {    
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');            
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$acrive_user:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){
            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            // 設定
            $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n';	// APIキー
            $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA';	// APIシークレット
            $access_token = $u_info[0]['access_token'];	// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];	// アクセストークンシークレット                    
            $request_url = 'https://api.twitter.com/1.1/friendships/destroy.json' ;	// エンドポイント
            $request_method = 'POST' ;
    
            // パラメータA (オプション)
            $params_a = array(
        //        "user_id" => "1528352858",
                "screen_name" => $username,
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
            $obj = json_decode( $json );
            //Log::debug('$obj アンフォローの結果=>:'.print_r($obj,true));
            if($obj){
                if(empty($obj->errors)){ 
                    Log::debug('アンフォローに成功しました！');
                    return array(
                        'res' => 'OK',
                        'msg' => 'アンフォローに成功しました',
                        'rst' => array(
                            'id' => $obj->id_str,
                            'name' => $obj->screen_name,
                            'text' => $obj->description,
                            'created_at' => date('Y:m:d h:i:s')
                        )
                    );
                    // //アンフォロー済リストとしてDBに追加する                
                    // $word_id = uniqid();//一意のIDを発行する
                    // $type = 2;//0:ターゲットアカウント 1:フォロー済アカウント 2:アンフォローアカウント
                    // $id = $u_info[0]['id'];
                    // try{
                    //     $data = array();
                    //     $data['id'] = $word_id;
                    //     $data['account_id'] = $id;
                    //     $data['screen_name'] = $username;
                    //     $data['type'] = $type; //0:ターゲットアカウント 1:フォロー済アカウント 2:アンフォローアカウント
                    //     $data['delete_flg'] = 0;
                    //     $data['created_at'] = date('Y:m:d h:i:s');
                    //     $post = Model_Useraccount::forge();
                    //     $post->set($data);
                    //     $rst = $post->save();//ユーザーアカウントをDBに保存する
                    //     Log::debug('$rst=>:'.print_r($rst,true));    
                    //     if($rst){
                    //         Log::debug('アンフォロー済リストに追加しました！');
                    //         return array(
                    //             'res' => 'OK',
                    //             'msg' => 'アンフォローに成功しました',
                    //             'rst' => array(
                    //                 'id' => $obj->id_str,
                    //                 'name' => $obj->screen_name,
                    //                 'text' => $obj->description,
                    //                 'created_at' => $data['created_at']
                    //             )
                    //         );
                    //     }else{
                    //         Log::debug('アンフォロー済リストに追加できませんでした。。');
                    //         return array(
                    //             'res' => 'NG',
                    //             'msg' => 'アンフォローに失敗しました',
                    //             'rst' => array(
                    //                 'id' => $obj->id_str,
                    //                 'name' => $obj->screen_name,
                    //                 'text' => $obj->description,
                    //                 'created_at' => $data['created_at']
                    //             )
                    //         );
                    //     }
    
    
                    // }catch(Exception $e) {
                    //     return array(
                    //         'res' => 'NG',
                    //         'msg' => $e->getMessage(),
                    //         'rst' => null
                    //     );
                    // }            
    
                }else{
                    if($obj->errors[0]->code===161){
                        //これ以上フォローできない状態
                        //３時間以上あけないと解除されないみたいなのでメッセージをそれ用に変える
                        return array(
                            'res' => 'UNFOLLOWLIMIT',
                            'msg' => 'これ以上アンフォローすることができません。３時間以上時間をおいてフォローを再開してください',
                            'rst' => $obj
                        );
                    }else{
                        Log::debug('$obj アンフォローに失敗');
                        // Log::debug('$obj アンフォローに失敗=>:'.print_r($obj,true));
                        return array(
                            'res' => 'NG',
                            'msg' => 'アンフォローに失敗しました',
                            'rst' => $obj
                        );
                    }
                }  
    
            }else{
                //$objが空の場合
                //１．アクセス制限である
                //２．アカウントが非公開
                //３．ネット環境が悪い
                Log::debug('$objが空です！！アンフォローに失敗');
                // Log::debug('$objが空です！！アンフォローに失敗 =>:'.print_r($obj,true));
                return array(
                    'res' => 'NG',
                    'msg' => 'アンフォローに失敗しました',
                    'rst' => $obj
                );
            }
        }

    }


    /**
     * $usernameが非アクティブユーザーかどうかをチェックする（非アクティブユーザー⇒15日間投稿なし）
     * 
     * @param $username アカウントID
     * @return　アクティブ：true 　非アクティブ：false 判定できなかった:null
    **/
    public function checkIfActiveuser($username)
    {        
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$acrive_user:'.print_r($screen_name,true));
        if($u_id !== null && $screen_name !== null){
            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            // 設定
            $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n';	// APIキー
            $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA';	// APIシークレット
            $access_token = $u_info[0]['access_token'];	// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];	// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/statuses/user_timeline.json' ;		// エンドポイント
            $request_method = 'GET' ;
    
            // パラメータA (オプション)
            $params_a = array(
        //        "user_id" => "1528352858",
        		"screen_name" => $username,
        //		"since_id" => "643299864344788992",
        //		"max_id" => "643299864344788992",
        		"count" => "1",
        //		"trim_user" => "true",
        //		"exclude_replies" => "true",
        //		"contributor_details" => "true",
        //		"include_rts" => "true",
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
            $obj_header = $this->header_decode($header);
            Log::debug('$obj@checkIfActiveuser');
            Log::debug('$obj_header@checkIfActiveuser');
            // Log::debug('$obj@checkIfActiveuser =>:'.print_r($obj,true));
            // Log::debug('$obj_header@checkIfActiveuser =>:'.print_r($obj_header,true));
            if($obj){
                if(empty($obj->error)){ 
                    if(empty($obj->errors)){
                        $now=new \DateTime();
                        $now_format = $now->format('Y-m-d');
                        $date_active = $now->modify('-15 days')->format('Y-m-d'); //15日間投稿がなかったら非アクティブユーザーと判定する
                        //アクティブ化どうかチェックする
                        foreach($obj as $key => $val){
                            $date_tl = $val->created_at;
                            $date_tl_format = date('Y-m-d', strtotime($date_tl));
                            Log::debug('$最終ツイート日付 =>:'.print_r($date_tl_format,true));
                            Log::debug('$この日付よりも新しかったらアクティブ =>:'.print_r($date_active,true));
                            if($date_tl_format > $date_active){
                                Log::debug('比較した結果 =>:'.print_r('アクティブです',true));
                                return array(
                                    'res' => 'OK',
                                    'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                    'rst' => true
                                );
                            }else{
                                Log::debug('比較した結果 =>:'.print_r('非アクティブです',true));
                                return array(
                                    'res' => 'OK',
                                    'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                    'rst' => false
                                );
                            }
                        }
                    }else{
                        //１．ページが存在しない
                        Log::debug('$obj タイムライン取得に失敗');
                        // Log::debug('$obj タイムライン取得に失敗=>:'.print_r($obj,true));

                        //アクセス制限にかかった場合はLIMITを返す
                        if($obj_header['x-rate-limit-remaining'] < 1) {
                            Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                            
                            //リクエスト上限に引っかかった
                            return array(
                                'res' => 'LIMIT',
                                'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                'rst' => null
                            );
                        }

                        return array(
                                'res' => 'NG',
                                'msg' => 'なにかしらのエラーが発生しました',
                                'rst' => null
                            );
                                
                    }
    
                }else{
                    //１．アカウントが非公開
                    //２．
                    Log::debug('$obj タイムライン取得に失敗');
                    // Log::debug('$obj タイムライン取得に失敗=>:'.print_r($obj,true));

                    //アクセス制限にかかった場合はLIMITを返す
                    if($obj_header['x-rate-limit-remaining'] < 1) {
                        Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                        
                        //リクエスト上限に引っかかった
                        return array(
                            'res' => 'LIMIT',
                            'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                            'rst' => null
                        );
                    }

                    return array(
                                'res' => 'NG',
                                'msg' => 'なにかしらのエラーが発生しました',
                                'rst' => null
                            );
                    
                }  
    
            }else{
                //$objが空の場合
                //１．アクセス制限である                
                //２．ネット環境が悪い
                Log::debug('$objが空です！！タイムライン取得に失敗');
                return null;
            }



        }


    }

    /**
     * $usernameArrayすべてのアカウントにおいてフレンドシップをチェックする
     * 
     * @param $usernameArray {username1, username2, username3, ・・・}
     * @return　obj
    **/
    public function checkFriendship($usernameArray)
    {
        $u_id = Session::get('user_id');
        $screen_name = Session::get('active_user');
        Log::debug('$u_id:'.print_r($u_id,true));
        Log::debug('$acrive_user:'.print_r($screen_name,true));

        $usernameArray_pick=array();
        foreach($usernameArray as $key => $val){
            if($key>99) break;//同時に100アカウントまでしかチェックできない　
            $usernameArray_pick[]=$val;            
        }

        if($u_id !== null && $screen_name !== null){
            
            $u_info = Db::get_userInfo($u_id, $screen_name);
            Log::debug('$u_info:'.print_r($u_info,true));
            if(!$u_info) return false;

            // 設定
            $api_key = 'PL2EEcGoYzjCRcfY8TA48wE1n';	// APIキー
            $api_secret = 'o69dKBhGCNChijJM029NB30T2hp6zQXKpCZsYul6kAnMLlNGLA';	// APIシークレット
            $access_token = $u_info[0]['access_token'];	// アクセストークン
            $access_token_secret = $u_info[0]['access_token_secret'];	// アクセストークンシークレット
            $request_url = 'https://api.twitter.com/1.1/friendships/lookup.json' ;		// エンドポイント
            $request_method = 'GET' ;

            // パラメータA (オプション)
            $params_a = array(
        //        "user_id" => "1528352858,2905085521",
        		"screen_name" => implode(",", $usernameArray_pick),
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
          
            $obj_header = $this->header_decode($header);
            Log::debug('$obj@checkIfActiveuser');
            // Log::debug('$obj@checkIfActiveuser =>:'.print_r($obj,true));
            if($obj){
                if(empty($obj->error)){ 
                    if(empty($obj->errors)){

                            return array(
                                'res' => 'OK',
                                'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                'rst' => $obj
                            );
                           
                        
                    }else{
                        //１．ページが存在しない
                        Log::debug('$obj タイムライン取得に失敗@checkfriendship');

                        //アクセス制限にかかった場合はLIMITを返す
                        if($obj_header['x-rate-limit-remaining'] < 1) {
                            Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                            
                            //リクエスト上限に引っかかった
                            return array(
                                'res' => 'LIMIT',
                                'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                                'rst' => null
                            );
                        }

                        switch($obj->errors[0]->code){
                            //アカウントが非公開の場合
                            case 179:
                                return array(
                                    'res' => 'PRIVATE',
                                    'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                    'rst' => null
                                );
                                break;

                            //SPAMから守るためにアカウントを一時停止した
                            case 326:
                                return array(
                                    'res' => 'SPAM',
                                    'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                    'rst' => null
                                );
                                break;

                            // case 34:
                            //     //Sorry, that page does not exist
                            //     break;

                            default:
                                return array(
                                    'res' => 'NG',
                                    'msg' => 'なにかしらのエラーが発生しました',
                                    'rst' => null
                                );
                        }
                                
                    }
    
                }else{
                    //１．アカウントが非公開
                    //２．
                    Log::debug('$obj タイムライン取得に失敗');
                    // Log::debug('$obj タイムライン取得に失敗=>:'.print_r($obj,true));

                    //アクセス制限にかかった場合はLIMITを返す
                    if($obj_header['x-rate-limit-remaining'] < 1) {
                        Log::debug('フォロワー取得上限です！自動フォローを一時中断します');
                        
                        //リクエスト上限に引っかかった
                        return array(
                            'res' => 'LIMIT',
                            'msg' => 'フォロワー取得上限になりました(TwitterAPI)',
                            'rst' => null
                        );
                    }

                    switch($obj->error[0]->code){
                        //アカウントが非公開の場合
                        case 179:
                            return array(
                                'res' => 'PRIVATE',
                                'msg' => '非公開のユーザーです。このユーザーはスキップします',
                                'rst' => null
                            );
                            break;

                        //SPAMから守るためにアカウントを一時停止した
                        case 326:
                            return array(
                                'res' => 'SPAM',
                                'msg' => 'SPAMから守るためアカウントを一時停止しました',
                                'rst' => null
                            );
                            break;

                        default:
                            return array(
                                'res' => 'NG',
                                'msg' => 'なにかしらのエラーが発生しました',
                                'rst' => null
                            );
                            break;
                    }
                    
                }  
    
            }else{
                //$objが空の場合
                //１．アクセス制限である                
                //２．ネット環境が悪い
                Log::debug('$objが空です！！タイムライン取得に失敗');
                return null;
            }



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
        $result_or=false; //ORキーワードで調査した結果
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
                if(strpos($text,$val['word']) !== false){
                    //含まれていない場合
                    $result_or=true;
                }
            }

            if($val['logic'] === '2'){
                //NOTで登録したキーワード
                //NOTキーワードは含まれている時点で$result_notをfalse
                if(strpos($text,$val['word']) !== false){
                    //含まれている場合
                    $result_not=true;
                    break;
                }
            }

            if(!preg_match( "/[ぁ-ん]+|[ァ-ヴー]+/u", $text)){
                //日本語が含まれていない場合は、falseを返す
                //$result_notをtrueにしてreturn falseになるようにする
                $result_not=true;
                break;
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

   

    /**
     * $textにフォロワーターゲットキーワードが含まれるか調べる
     * 
     * @param MAILTYPE
     * @return　送信成功:true 　送信失敗：false
    **/
    public function mailTo($type)
    {
        Log::debug('メール送信します:'.print_r($type,true));
        Log::debug('MAILTYPE::FINISH_AUTOFOLLOW:'.print_r(MAILTYPE::FINISH_AUTOFOLLOW,true));
        Log::debug('MAILTYPE::FINISH_AUTOFOLLOW:'.print_r(MAILTYPE::FINISH_AUTOLIKE,true));
        //ユーザーID取得
        $u_id = Session::get('user_id');
        Log::debug('$u_id:'.print_r($u_id,true));
        if($u_id !== null){
            try{
            //メールアドレスを取得する
            $mailtoArray = Db::get_email($u_id);
            $mailto = $mailtoArray[0]['email'];
            Log::debug('$mailto:'.print_r($mailto,true));
            $mailfrom = "From:webukatsutest@service-1.masashisite.com";
            $subject = '';            
            $content = '';
            switch($type){
    
                case MAILTYPE::FINISH_AUTOFOLLOW:
                    $subject = "【自動フォロー完了】｜神ったー";
                    $content = <<<EOT
自動フォローが完了しました。
現在登録しているターゲットアカウントを削除し、新たにターゲットアカウントを新規に登録するか、
フォロワーサーチキーワードを変えてみてください！

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
                case MAILTYPE::FINISH_AUTOLIKE:
                    $subject = "【自動いいね完了】｜神ったー";
                    $content = <<<EOT
自動いいねが完了しました。
キーワードを新たに登録してみよう！

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
                case MAILTYPE::FINISH_AUTOUNFOLLOW:
                    $subject = "【自動フォロー解除完了】｜神ったー";
                    $content = <<<EOT
自動フォロー解除が完了しました。
【自動フォロー解除動作条件】
・フォロー数が5000人以上
・15分間隔

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
                case MAILTYPE::ERROR_REQUEST_AUTOFOLLOW:
                    $subject = "【自動フォロー制限中】｜神ったー";
                    $content = <<<EOT
下記の理由で自動フォロー制限中です。
制限解除されたら自動的に再開します。

・一日のフォロー上限になった
・フォローを短時間で大量に実施した

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
                case MAILTYPE::ERROR_REQUEST_AUTOLIKE:
                    $subject = "【自動いいね制限中】｜神ったー";
                    $content = <<<EOT
下記の理由により自動いいね制限中です。
制限解除されたら自動的に再開します。

・短時間でいいねしすぎた

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
                case MAILTYPE::ERROR_REQUEST_AUTOUNFOLLOW:
                    $subject = "【自動フォロー解除制限中】｜神ったー";
                    $content = <<<EOT
下記の理由により自動フォロー解除制限中です。
制限解除されたら自動的に再開します。

・短時間でフォロー解除しすぎた

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
                case MAILTYPE::ERROR_SPAM_DETECTED:
                    $subject = "【機能停止連絡】｜神ったー";
                    $content = <<<EOT
アカウントの機能が制限されています。
https://twitter.com にログインして制限解除してください。

////////////////////////////////////////
カスタマーセンター
URL  https://masashisite.com/
E-mail fktlnz@gmail.com
////////////////////////////////////////
EOT;
                    break;
            }            
            // 文字化けするようなら下記のコメントアウト解除してみて
            // mb_language("ja");
            mb_internal_encoding("UTF-8");
            
            // メール送信処理
            $result = mb_send_mail($mailto,$subject,$content,$mailfrom);

            //結果をフロントに返す
            if($result){
                Log::debug('メール送信完了');
                return true;         
            }else{
                Log::debug('メール送信に失敗しました');
                return false;     
            }

            }catch(Exception $e){
                Log::debug('メール送信に失敗しました!');
                return false;  
            }
            

        }else{
            return false;
        }
    }
    


}