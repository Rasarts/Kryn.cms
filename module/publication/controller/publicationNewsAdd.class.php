<?php

class publicationNewsAdd extends windowAdd {

    //public $table = 'publication_news';
    public $object = 'news';

    //public $primary = array('id');
    //public $multiLanguage = true;

    public function saveItem() {
        parent::saveItem();
        kryn::invalidateCache('publicationNewsList');
    }

    public $tabFields = array(
        'General' => array(
            'title',
            'category_id' => array(
                'label' => 'Category',
                'type' => 'select',
                'multiLanguage' => true,
                'empty' => false,
                'table' => 'publication_news_category',
                'table_label' => 'title',
                'table_key' => 'id'
            ),
            'tags' => array(
                'label' => 'Tags',
                'type' => 'text'
            ),
            'introimage',
            'introimage2' => array(
                'label' => 'Intro image 2',
                'type' => 'fileChooser'
            ),
        ),
        'Access' => array(
            'releaseat' => array(
                'label' => 'Release at',
                'desc' => 'If you want to release the news now, let it empty',
                'type' => 'datetime',
            ),
            'releasedate' => array(
                'label' => 'News date',
                'type' => 'datetime',
                'empty' => false
            ),
            'deactivate' => array(
                'label' => 'Hide',
                'type' => 'checkbox'
            ),
            'deactivatecomments' => array(
                'label' => 'Deactivate comments (override plugin properties)',
                'type' => 'checkbox'
            )
        ),
        'Intro' => array(
            'intro' => array(
                'label' => 'Intro',
                'type' => 'layoutelement'
            )
        ),
        'Content' => array(
            'content' => array(
                'label' => 'Content',
                'type' => 'layoutelement'
            )
        ),
        'Files' => array(
            'files' => array(
                'label' => 'Files',
                'type' => 'fileList',
                'size' => 10,
                'width' => 500
            )
        )
    );
}

?>
