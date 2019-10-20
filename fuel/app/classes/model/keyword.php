<?php 
class Model_Keyword extends \Orm\Model {
    protected static $_connection = 'mysqli';
    protected static $_table_name = 'keyword';
    protected static $_properties = array(
        'id',
        'account_id' => array(
            'data_type'  => 'int',
            'label'      => 'アカウントID',
        ),
        'word' => array(
            'data_type'  => 'varchar',
            'label'      => 'キーワード',
        ),
        'logic' => array(
            'data_type'  => 'int',
            'label'      => 'ロジック（0:and, 1:or, 2:not）',
        ),
        'type' => array(
            'data_type'  => 'int',
            'label'      => 'キーワードタイプ（0:followerSearchWord, 1:likeWord）',
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