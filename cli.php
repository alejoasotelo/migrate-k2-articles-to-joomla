<?php

require_once __DIR__ . '/bootstrap.php';

class K2ToJoomlaCli extends JApplicationCli
{
    const MAP_CATEGORIES_FILE = __DIR__ . '/mapCategories.json';
    const PAGINATION_FILE = __DIR__ . '/pagination.json';

    protected $pagination = false;

    /**
     * Entry point for the script
     *
     * @return  void
     *
     * @since   3.8.6
     */
    public function doExecute()
    {
		$userId = $this->input->getInt('userid', 0);
		$limit = $this->input->getInt('limit', 20);
		$baseUrl = $this->input->getCwd('baseUrl', JPATH_ROOT);
		$destImageUrl = $this->input->getCwd('destImageUrl', null);
		$categoryName = $this->input->getString('categoryName', 'Categoría');

        if ($userId == 0) {
            $this->out('Es necesario el --userid de un Usuario Administrador para generar los articulos y categorias', true);
            return false;
        }

        if ($limit <= 0) {
            $this->out('El campo --limit tiene que ser mayor a 0', true);
            return false;
        }

        if (empty($categoryName)) {
            $categoryName = 'Categoría';
        } 

        $migration = new Migration($this, $userId, $baseUrl, $destImageUrl);

        $mapCategories = $this->loadMapCategories();

        if ($mapCategories) {
            $this->out('======= Reanudando migracion =======', true);

            $migration->setMapCategories($mapCategories);

        } else {

            $this->out('======= Empezando migracion =======', true);

            $this->out(' (!) Migrando Categorias...', true);

            $migration->migrateK2Categories($categoryName);

            $this->saveMapCategories($migration->getMapCategories());

            $this->out(' (Ok) Categorias Migradas', true);

        }

        /**
         * mapArticles = new stdClass
         * $mapArticles->count = 1000
         * $mapArticles->page = 0
         * $mapArticles->limit = 100
         */

        $this->pagination = $this->loadPagination();

        if ($this->pagination) {

            $this->out(' (->) Reanudando migración Artículos...', true);

        } else {

            $this->out(' (!) Migrando Artículos...', true);

            $this->pagination = new stdClass();
            $this->pagination->limit = $limit;
            $this->pagination->countArticles = $migration->getK2ArticlesCount();
            $this->pagination->pages = ceil($this->pagination->countArticles / $this->pagination->limit);
            $this->pagination->currentPage = 0;

        }

        $this->out('     Artículos: ' . $this->pagination->countArticles, true);
        $this->out('     Páginas: ' . $this->pagination->pages, true);

        for ($i = $this->pagination->currentPage; $i < $this->pagination->pages; $i++) {
            $this->pagination->currentPage = $i;

            try{
                $migration->migrateK2Articles($this->pagination->currentPage, $this->pagination->limit);

                // Como ya migró guardo la paginación para la próxima página, sino cuando se reanude la migración va a volver a copiar los mismos artículos.
                $this->pagination->currentPage++;

                $this->savePagination($this->pagination);
    
                $this->out('     Página ' . $this->pagination->currentPage . ' de ' . $this->pagination->pages . ', limit: ' . $this->pagination->limit, true);

            } catch(Exception $e) {
                $this->savePagination($this->pagination);
                $this->out('     Exception: ' . $e->getMessage(), true);
            }

        }

        $this->out(' (Ok) Articulos migrados', true);

        // Como terminó la migración eliminó los archivos.
        unlink(self::MAP_CATEGORIES_FILE);
        unlink(self::PAGINATION_FILE);

        $this->out('======= Migracion Completada =======', true);
    }

    public function saveMapCategories($mapCategories) {
        file_put_contents(self::MAP_CATEGORIES_FILE, serialize($mapCategories));        
    }

    public function loadMapCategories() {
        
        if (file_exists(self::MAP_CATEGORIES_FILE)) {
            return unserialize(file_get_contents(self::MAP_CATEGORIES_FILE));
        }

        return false;

    }

    public function savePagination($pagination) {
        file_put_contents(self::PAGINATION_FILE, serialize($pagination));        
    }

    public function loadPagination() {
        
        if (file_exists(self::PAGINATION_FILE)) {
            return unserialize(file_get_contents(self::PAGINATION_FILE));
        }

        return false;

    }
}

JApplicationCli::getInstance('K2ToJoomlaCli')->execute();