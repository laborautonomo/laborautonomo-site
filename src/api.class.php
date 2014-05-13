<?php 
use Silex\Application;

namespace ApiClient;

class Repositories {
    
    private $_app;
    public  $repos;
    public  $repos_url;
    public  $readme_store;
    public  $releases_store;
    public  $repos_list_filename;
    public  $repos_languages;
    
    function __construct(\Silex\Application $app){
        $this->_app                 = $app;
        $this->repos                = "https://api.github.com/users/{$this->_app['repos.config']['github']['user']}/repos?sort=updated&direction=desc";
        $this->repos_url            = "https://api.github.com/repos/{$this->_app['repos.config']['github']['user']}/";
        $this->readme_store         = $this->_app['repos.config']['storage'] . 'pages/';
        $this->releases_store       = $this->_app['repos.config']['storage'] . 'releases/';
        $this->repos_list_filename  = 'repos-' . date('Y-m-d') . '.json';
        $this->repos_languages      = $this->_app['repos.config']['storage'] . 'repos-languages-' . date('Y-m-d') . '.json';
    }
    
    private function _get_json($url,$params = array()){
        $cURL = curl_init($url);
        curl_setopt($cURL, CURLOPT_USERAGENT, $this->_app['repos.config']['github']['repo']);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        
        foreach ($params as $name => $value) curl_setopt($cURL, $name, $value);
        
        $json = curl_exec($cURL);
        curl_close($cURL);
        
        return $json;
    }
    
    public function list_all(){
        if (file_exists($this->_app['repos.config']['storage'] . $this->repos_list_filename)) 
            return file_get_contents($this->_app['repos.config']['storage'] . $this->repos_list_filename);
        
        $this->garbage_collection();
        
        $data = $this->_get_json($this->repos);
        file_put_contents($this->_app['repos.config']['storage'] . $this->repos_list_filename, $data);
        
        return $data;
    }
    
    public function get_repositories($criteria = array()){
            
        $repos = json_decode($this->list_all(),true);
        
        if($criteria) {
            $repos = array_filter($repos, function($obj) use ($criteria){
                $return = true;
                
                foreach ($criteria as $k => $v) {
                    $return = ($obj[$k] == $v);
                }
                
                return $return;
            });
        }
        
        return json_encode(array_values($repos));
    }
    
    public function garbage_collection(){
        $dir = opendir($this->_app['repos.config']['storage']);

        while ($file = readdir($dir)) {
            if (preg_match('/^\./i', $file) || is_dir($this->_app['repos.config']['storage'] . $file)) 
                continue;
            
            unlink($this->_app['repos.config']['storage'] . $file);
        }
        
        unset($dir);
    }
    
    public function get_languages($criteria) {
        if (file_exists($this->repos_languages)) 
            $repos = file_get_contents($this->repos_languages);
        else
            $repos = $this->mount_languages_file(true);
        
        $repos = json_decode($repos,true);
        
        if($criteria) {
            $repos = array_filter($repos, function($obj) use ($criteria){
                $return = true;
                
                foreach ($criteria as $k => $v) {
                    $return = ($obj[$k] == $v);
                }
                
                return $return;
            });
        }
        
        return array_values($repos);
    }
    
    public function mount_languages_file($return_json=false) {
            
        $json_langs = '[';
        $repos = json_decode($this->get_repositories(array('fork' => false)),true);
        
        foreach ($repos as $key => $repo) {
            $json_langs .= '{"name":"' . $repo["name"] . '",';
            $json_langs .= '"languages":' . $this->_get_json($this->repos_url . $repo["name"] . '/languages') . '},';
        }
        
        $json_langs = substr($json_langs, 0, -1) . ']'; 
        
        file_put_contents($this->repos_languages, $json_langs);
        
        return ($return_json) ? $json_langs : true;
    }
    
    public function get_readme($name) {
        if (file_exists($this->readme_store . $name . '.html')) 
            return file_get_contents($this->readme_store . $name . '.html');
        
        return $this->download_readme(array('name' => $name), true);
    }
    
    public function download_readme($criteria = array('fork' => false), $return_html=false) {
        if(!is_dir($this->readme_store)) mkdir($this->readme_store, 0777, true);
        
        $repos = json_decode($this->get_repositories($criteria),true);
        
        foreach ($repos as $key => $repo) {
            $readme_html = $this->_get_json(
                $this->repos_url . $repo["name"] . '/readme', 
                array(CURLOPT_HTTPHEADER => array('Accept: application/vnd.github.v3.html+json'))
            );
            
            file_put_contents($this->readme_store . $repo["name"] . '.html', $readme_html);
        }
        
        return ($return_html) ? $readme_html : true;
    }
    
    public function update_project_pages() {
        $dir = opendir($this->readme_store);

        while ($file = readdir($dir)) {
            if (preg_match('/^\./i', $file)) continue;
            unlink($this->readme_store . $file);
        }
        
        unset($dir);
        
        $this->download_readme();
        
        return true;
    }
    
    public function get_releases($project) {
        if (file_exists($this->releases_store . $project . '.json')) 
            return file_get_contents($this->releases_store . $project . '.json');
        
        return $this->download_releases(array('name' => $project), true);
    }
    
    public function download_releases($criteria = array('fork' => false), $return_content=false) {
        if(!is_dir($this->releases_store)) mkdir($this->releases_store, 0777, true);
        
        $repos = json_decode($this->get_repositories($criteria),true);
        
        foreach ($repos as $key => $repo) {
            $content = $this->_get_json($this->repos_url . $repo["name"] . '/releases');
            
            file_put_contents($this->releases_store . $repo["name"] . '.json', $content);
        }
        
        return ($return_content) ? $content : true;
    }
    
    public function update_releases() {
        $dir = opendir($this->releases_store);

        while ($file = readdir($dir)) {
            if (preg_match('/^\./i', $file)) continue;
            unlink($this->releases_store . $file);
        }
        
        unset($dir);
        
        $this->download_releases();
        
        return true;
    }
}