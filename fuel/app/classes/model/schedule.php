<?php 
class Model_Schedule extends \Orm\Model {
    protected static $_connection = 'mysqli';
    protected static $_table_name = 'schedule';
    protected static $_properties = array(
        'id',
        'account_id' => array(
            'data_type'  => 'int',
            'label'      => 'アカウントID',
        ),
        'date' => array(
            'data_type'  => 'varchar',
            'label'      => 'スクリーンネーム',
        ),
        'text' => array(
            'data_type'  => 'int',
            'label'      => 'ユーザーID',
        ),
        'done_flg' => array(
            'data_type'  => 'tinyint',
            'label'      => 'アクセストークン',
        ),
        'delete_flg' => array(
            'data_type' => 'tinyint', 
            'label'     => 'delete flag',
        ),
        'created_at' => array(
            'data_type' => 'datetime',
            'label'     => '作成日',
        )
        
    );
}