<?php 
class Model_Signupform extends \Orm\Model {
    protected static $_connection = 'mysqli';
    protected static $_table_name = 'users';
    protected static $_properties = array(
        'id',
        'username' => array(
            'data_type'  => 'varchar',
            'label'      => 'ユーザー名',
            'validation' => array(
                'required', 
                'max_length' => array(255),
                'match_pattern' => array("/^[a-zA-Z0-9]+$/", '半角英数字'),
            ),
            // 'form'       => array(
            //     'type' => 'text',
            //     'placeholder' => '問題文',
            //     'class' => 'text-init form',
            //     'autocomplete' => 'off',
            // ),
        ),
        'password' => array(
            'data_type'  => 'varchar',
            'label'      => 'パスワード',
            'validation' => array(
                'required', 
                'max_length' => array(255),
                'min_length' => array(6),
            ),
            // 'form'       => array(
            //     'type' => 'text', 
            //     'placeholder' => '選択肢１',
            //     'class' => 'text-init form',
            //     'autocomplete' => 'off',            
            // ),
        ),
        'email' => array(
            'data_type'  => 'varchar',
            'label'      => 'Email',
            'validation' => array(
                'required', 
                'max_length' => array(255),
                'valid_emails',
            ),
            // 'form'       => array(
            //     'type' => 'text', 
            //     'placeholder' => '選択肢２',
            //     'class' => 'text-init form',
            //     'autocomplete' => 'off',                
            // ),
        ),        
        'create_time' => array(
            'data_type' => 'datetime',
            'label'     => 'Created At',
            'form'      => array('type' => false,),
        ),
        'delete_flg' => array(
            'data_type' => 'tinyint', 
            'label'     => 'delete flag',
            'form'      => array('type' => false),
        )
    );
}