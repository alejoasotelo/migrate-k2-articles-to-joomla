<?php

use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_content/models/article.php';
require_once JPATH_ADMINISTRATOR . '/components/com_categories/models/category.php';

class Migration
{

    protected $categoryModel = null;

    protected $articleModel = null;

    protected $mapCategories = [];

    protected $userId = 0;
    
    protected $baseUrl = JPATH_ROOT;

    protected $destImageUrl = null;

    public function __construct($userId, $baseUrl = JPATH_ROOT, $destImageUrl = null)
    {
        $this->userId = $userId;
        $this->baseUrl = $baseUrl;
        $this->destImageUrl = is_null($destImageUrl) ? 'images/articulos/src' : $destImageUrl;

        $config = array('table_path' => JPATH_ADMINISTRATOR . '/components/com_categories/tables');
        $this->categoryModel = new CategoriesModelCategory($config);

        $config = array('table_path' => JPATH_ADMINISTRATOR . '/components/com_content/tables');
        $this->articleModel = new ContentModelArticle($config);
    }

    /**
     * Migra las categorias de K2 a categorías Joomla.
     *
     * @return void
     */
    public function migrateK2Categories()
    {
        // Obtengo las K2 Categories
        $k2Categories = $this->getK2CategoriesTree();

        // Creo la categoría Padre para todas las categorías de K2
        $category = (object)[
            'title' => 'Categoria K2',
            'alias' => 'categoria-k2-' . uniqid()
        ];

        $idCategoriaInicial = $this->createJoomlaCategory($category);

        // Convierto las K2 Categories en Joomla Categories
        $this->createJoomlaCategories($k2Categories, $idCategoriaInicial);
    }

    /**
     * Devuelve el arbol de categorias K2 con los campos listos para categorías Joomla
     *
     * @param integer $offsetId Este valor se suma a los IDs de las categorías
     * @return Array<object> Categorías K2
     */
    public function getK2CategoriesTree($parentId = 0, $level = 1)
    {
        $dbo = \Joomla\CMS\Factory::getDbo();
        $query = $dbo->getQuery(true);

        $query
            ->select('id, name title, CONCAT(alias, "-k2") alias, published, access, parent parent_id, language, "com_content" extension, ' . $level . ' level, "" path, 0 asset_id')
            ->from('#__k2_categories')
            ->where('parent = ' . $parentId);

        $categories = $dbo->setQuery($query)->loadObjectList();

        foreach ($categories as &$category) {
            $children = $this->getK2CategoriesTree($category->id, $level + 1);

            $category->children = $children;
        }

        return $categories;
    }

    /**
     * Crea el arbol de Categorias Joomla pasando como parametro las categorias K2
     *
     * @param Array<Object> $k2Categories
     * @param integer $parentId = 0
     * @return void
     */
    public function createJoomlaCategories($k2Categories, $parentId = 0)
    {
        foreach ($k2Categories as $k2Category) {
            $categoryId = $this->createJoomlaCategory($k2Category, $parentId);

            $this->mapCategories[$k2Category->id] = $categoryId;

            if (count($k2Category->children)) {
                $this->createJoomlaCategories($k2Category->children, $categoryId);
            }
        }
    }

    /**
     * Crea una categoria Joomla
     *
     * @param object $category object con datos [title => '', alias => '']
     * @param integer $parentId
     * @return int|bool Si se crea bien retorna el ID, sino false
     */
    public function createJoomlaCategory($category, $parentId = 0)
    {
        $data = [];
        $data['id'] = 0; //(int)$category->id;
        $data['parent_id'] = $parentId;
        $data['title'] = $category->title;
        $data['alias'] = $category->alias;
        $data['extension'] = 'com_content';
        $data['published'] = '1';
        $data['language'] = '*';
        $data['params'] = array('category_layout' => '', 'image' => '');
        $data['metadata'] = array('author' => '', 'robots' => '');
        $data['rules'] = array(
            'core.edit.state' => array(),
            'core.edit.delete' => array(),
            'core.edit.edit' => array(),
            'core.edit.state' => array(),
            'core.edit.own' => array(1 => true)
        );

        if (!$this->categoryModel->save($data)) {
            //$err_msg = $this->categoryModel->getError();

            $data['alias'] .= '-' . $parentId;

            if (!$this->categoryModel->save($data)) {
                return false;
            }
        }

        return $this->categoryModel->getItem()->id;
    }

    public function migrateK2Articles($page = 0, $limit = 20)
    {
        $articlesMigrated = [];

        $k2Articles = $this->getK2Articles($page, $limit);

        foreach ($k2Articles as $k2Article) {
            $idJoomlaCategory = $this->mapCategories[$k2Article->catid];
                
            if ($idJoomlaCategory > 0) {
                $idJoomlaArticle = $this->createJoomlaArticle($k2Article, $idJoomlaCategory);

                $articlesMigrated[] = [
                    'idK2' => $k2Article->id,
                    'idJoomla' => $idJoomlaArticle,
                    'success' => $idJoomlaArticle > 0 
                ];
            }
        }

        return $articlesMigrated;
    }

    public function getK2Articles($page = 0, $limit = 20)
    {
        $dbo = \Joomla\CMS\Factory::getDbo();
        $query = $dbo->getQuery(true);

        $query
            ->select('`id`, `title`, `alias`, `introtext`, `fulltext`, `created`, `catid`')
            ->select($this->userId . ' as `created_by`, ' . $this->userId . ' as `modified_by`, `published`')
            ->selecT('`created_by_alias`, `checked_out`, `checked_out_time`, `modified`, `publish_up`, `publish_down`, `access`, `featured`, `hits`, `language`')
            ->from('#__k2_items')
            //->where('catid = ' . $idK2Category)
            ->order('id ASC')
            ->setLimit($limit, $page * $limit);

        return $dbo->setQuery($query)->loadObjectList();
    }

    public function getK2ArticlesCount()
    {
        $dbo = \Joomla\CMS\Factory::getDbo();
        $query = $dbo->getQuery(true);

        $query
            ->select('count(*)')
            ->from('#__k2_items');

        return (int)$dbo->setQuery($query)->loadResult();
    }

    public function getK2ImagePath($id) {
        $filename = md5("Image" . $id);
        //$filepath = $this->baseUrl . '/media/k2/items/src/'.$filename.'.jpg';

        //if (JFile::exists($filepath) || $this->existsRemoteImage($filepath)) {
            return $this->destImageUrl . '/'.$filename.'.jpg';
        //}

        //return '';
    }

    public function existsRemoteImage($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        // don't download content
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== FALSE;
    }

    /**
     * Crea una categoria Joomla
     *
     * @param object $category object con datos [title => '', alias => '']
     * @param integer $parentId
     * @return int|bool Si se crea bien retorna el ID, sino false
     */
    public function createJoomlaArticle($article, $catId = 0)
    {
        $data = [];
        $data['id'] = 0;
        $data['catid'] = $catId;
        $data['title'] = $article->title;
        $data['alias'] = $article->alias . '-k2';
        $data['published'] = $article->published;
        $data['state'] = $article->published;
        $data['language'] = '*';
        $data['introtext'] = $article->introtext;
        $data['fulltext'] = $article->fulltext;
        $data['created'] = $article->created;
        $data['created_by'] = $article->created_by;
        $data['created_by_alias'] = $article->created_by_alias;
        $data['modified'] = $article->modified;
        $data['modified_by'] = $article->modified_by;
        $data['checked_out'] = $article->checked_out;
        $data['checked_out_time'] = $article->checked_out_time;
        $data['publish_up'] = $article->publish_up;
        $data['publish_down'] = $article->publish_down;
        $data['access'] = $article->access;
        $data['featured'] = $article->featured;
        $data['hits'] = $article->hits;

        $image = $this->getK2ImagePath($article->id);

        if (!empty($image)) {
            $images = new Registry;
            $images->set('image_intro', $image);
            $images->set('float_into', '');
            $images->set('image_intro_alt', '');
            $images->set('image_intro_caption', '');
            $images->set('image_fulltext', '');
            $images->set('float_fulltext', '');
            $images->set('image_fulltext_alt', '');
            $images->set('image_fulltext_caption', '');
    
            $data['images'] = (string)$images;
        }

        if (!$this->articleModel->save($data)) {
            //$err_msg = $this->articleModel->getError();

            $data['alias'] .= '-' . $catId;

            if (!$this->articleModel->save($data)) {
                return false;
            }
        }

        return $this->articleModel->getItem()->id;
    }

    public function getMapCategories() {
        return $this->mapCategories;
    }

    public function setMapCategories($mapCategories) {
        return $this->mapCategories = $mapCategories;
    }
}
