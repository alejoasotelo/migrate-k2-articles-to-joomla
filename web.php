<?php

require_once __DIR__ . '/bootstrap.php';

class K2ToJoomlaWeb extends JApplicationWeb
{
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
		$type = $this->input->getCmd('type', '');
		$isAjax = $this->input->getBool('ajax', false);

        if ($userId == 0) {
            echo 'Es necesario el --userid de un Usuario Administrador para generar los articulos y categorias<br>';
            return false;
        }        

        $migration = new Migration($userId);

        $start = time();
        
        if (!$isAjax) {
            echo '======= Empezando Migracion  - ' . date('h:i') . 'hs =======<br><br>';
        }

        if ($type == 'categories') {
            $migration->migrateK2Categories();

            if ($isAjax) {
                echo json_encode([
                    'mapCategories' => $migration->getMapCategories(),
                    'countArticles' => $migration->getK2ArticlesCount()
                ]);
            } else {
                echo ' Categorias Migradas<br>';
            }

        } else if ($type == 'articles') {
            $data = $this->input->json->getRaw();
            $limit = $this->input->getInt('limit', 20);
            $page = $this->input->getInt('page', 0);

            if (empty($data)) {
                die();
            }
            
            $data = json_decode($data);
            $mapCategories = (array)$data->mapCategories;

            if (count($mapCategories) == 0) {
                if (!$isAjax) {
                    echo ' Mapa de categorias inválido.';
                }
                exit;
            }

            $migration->setMapCategories($mapCategories);

            $migrated = $migration->migrateK2Articles($page, $limit);
            
            if ($isAjax) {
                echo json_encode([
                    'page' => $page,
                    'limit' => $limit,
                    'countArticles' => $migration->getK2ArticlesCount(),
                    'migrated' => $migrated
                ]);
            } else {
                echo ' Articulos migrados<br>';
            }
        }


        $end = time(); 

        if (!$isAjax) {
            echo ' Duración de la migración: ' . ($end - $start);
            echo '<br><br>======= Migracion Completada  - ' . date('h:i') . 'hs  =======';
        }
    }
}

JApplicationWeb::getInstance('K2ToJoomlaWeb')->execute();