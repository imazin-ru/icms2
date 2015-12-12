<?php

class actionAdminInstallFinish extends cmsAction {

    public function run(){

        $config = cmsConfig::getInstance();

        $path = $config->upload_path . $this->installer_upload_path;
        $path_relative = $config->upload_root . $this->installer_upload_path;

        $installer_path = $path . '/' . 'install.php';
        $sql_dump_path = $path . '/' . 'install.sql';

		$is_imported  = $this->importPackageDump($sql_dump_path);
        $is_installed = $this->runPackageInstaller($installer_path);

        // считаем, что пришла ошибка
        if(is_string($is_installed)){

            cmsUser::addSessionMessage($is_installed, 'error');

            $this->redirectToAction('install');

        }

        $redirect_action = '';

        if($is_imported && $is_installed === true){
            $redirect_action = $this->doPackage();
        }

        $is_cleared = files_clear_directory($path);

        return cmsTemplate::getInstance()->render('install_finish', array(
            'is_cleared'      => $is_cleared,
            'redirect_action' => $redirect_action,
            'path_relative'   => $path_relative
        ));

    }

    private function doPackage() {

        $manifest = $this->parsePackageManifest();

        if(isset($manifest['package'])) {

            return call_user_func(array($this, $manifest['package']['type'].$manifest['package']['action']), $manifest);

        }

        $cache = cmsCache::getInstance();

        $cache->clean('controllers');
        $cache->clean('events');

        return '';

    }

    private function componentInstall($manifest) {

        $model = new cmsModel();

        $controller_root_path = cmsConfig::get('root_path').'system/controllers/'.$manifest['package']['name'].'/';

        $form_file = $controller_root_path.'backend/forms/form_options.php';
        $form_name = $manifest['package']['name'] . 'options';

        cmsCore::loadControllerLanguage($manifest['package']['name']);

        $form = cmsForm::getForm($form_file, $form_name, false);
        if ($form) {
            $options = $form->parse(new cmsRequest(array()));
        } else {
            $options = null;
        }

        $model->insert('controllers', array(
            'title'      => $manifest['info']['title'],
            'name'       => $manifest['package']['name'],
            'options'    => $options,
            'author'     => (isset($manifest['author']['name']) ? $manifest['author']['name'] : LANG_CP_PACKAGE_NONAME),
            'url'        => (isset($manifest['author']['url']) ? $manifest['author']['url'] : null),
            'version'    => $manifest['version']['major'] . '.' . $manifest['version']['minor'] . '.' . $manifest['version']['build'],
            'is_backend' => file_exists($controller_root_path.'backend.php')
        ));

        return 'controllers';

    }
    private function componentUpdate($manifest) {

        $model = new cmsModel();

        $controller_root_path = cmsConfig::get('root_path').'system/controllers/'.$manifest['package']['name'].'/';

        $form_file = $controller_root_path.'backend/forms/form_options.php';
        $form_name = $manifest['package']['name'] . 'options';

        cmsCore::loadControllerLanguage($manifest['package']['name']);

        $form = cmsForm::getForm($form_file, $form_name, false);
        if ($form) {
            $options = $form->parse(new cmsRequest(cmsController::loadOptions($manifest['package']['name'])));
        } else {
            $options = null;
        }

        $model->filterEqual('name', $manifest['package']['name'])->updateFiltered('controllers', array(
            'title'      => $manifest['info']['title'],
            'options'    => $options,
            'author'     => (isset($manifest['author']['name']) ? $manifest['author']['name'] : LANG_CP_PACKAGE_NONAME),
            'url'        => (isset($manifest['author']['url']) ? $manifest['author']['url'] : null),
            'version'    => $manifest['version']['major'] . '.' . $manifest['version']['minor'] . '.' . $manifest['version']['build'],
            'is_backend' => file_exists($controller_root_path.'backend.php')
        ));

        return 'controllers';

    }

    private function widgetInstall($manifest) {

        $model = new cmsModel();

        $model->insert('widgets', array(
            'title'      => $manifest['info']['title'],
            'name'       => $manifest['package']['name'],
            'controller' => $manifest['package']['controller'],
            'author'     => (isset($manifest['author']['name']) ? $manifest['author']['name'] : LANG_CP_PACKAGE_NONAME),
            'url'        => (isset($manifest['author']['url']) ? $manifest['author']['url'] : null),
            'version'    => $manifest['version']['major'] . '.' . $manifest['version']['minor'] . '.' . $manifest['version']['build']
        ));

        return 'widgets';

    }

    private function widgetUpdate($manifest) {

        $model = new cmsModel();

        $model->filterEqual('name', $manifest['package']['name'])->
                filterEqual('controller', $manifest['package']['controller'])->
                updateFiltered('widgets', array(
            'title'      => $manifest['info']['title'],
            'author'     => (isset($manifest['author']['name']) ? $manifest['author']['name'] : LANG_CP_PACKAGE_NONAME),
            'url'        => (isset($manifest['author']['url']) ? $manifest['author']['url'] : null),
            'version'    => $manifest['version']['major'] . '.' . $manifest['version']['minor'] . '.' . $manifest['version']['build']
        ));

        return 'widgets';

    }

    private function systemInstall($manifest) {
        return '';
    }
    private function systemUpdate($manifest) {
        return '';
    }

    private function runPackageInstaller($file){

        // нет файла, считаем, что так задумано и ошибку не отдаем
        if (!file_exists($file)) { return true; }

        @chmod($file, 0666);

        include_once $file;

        if (!function_exists('install_package')){ return false; }

        return call_user_func('install_package');

    }

    private function importPackageDump($file){

        if (!file_exists($file)) { return true; }

        $db = cmsDatabase::getInstance();

        return $db->importDump($file);

    }

}
