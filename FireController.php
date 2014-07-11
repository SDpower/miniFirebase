<?php

/**
 * Description of FireController
 *
 * @author steve lo
 */
class FireController extends \Phalcon\Mvc\Controller
{
    private $redis;
    private $__init;
    const PREFX = 'fs2:';

    public function initialize()
    {
        if ($this->__init) {
            return;
        }
        $this->redis = $this->di->get('redis');
        $this->__init = true;
    }
    /**
     * 
     * @param string $key
     * @return object|boolen
     */
    private function getItems($keys,$root = false)
    {
        if ($root) {
            $scankey = ($keys == '') ? self::PREFX.'*':self::PREFX.$keys.':*';
            $keys = ($keys == '') ? substr(self::PREFX, 0,  strlen(self::PREFX) -1):self::PREFX.$keys;
        }else {
            $scankey = ($keys == '') ? '*':$keys.':*';
        }

        $allKeys = $this->redis->keys($scankey);
        if (count($allKeys) == 0){
             return $this->redis->get($keys);
        }
        $type = $this->redis->get($keys);

        if ($type == 'object' || $root == true) {
            $ret = new \stdClass();
            
            foreach ($allKeys as $key => $value) {
                $getKey = str_replace($keys.':', '', $value);
                if (strpos($getKey, ':') !== false) {
                } else {
                    if (strpos($getKey, ':') !== false) {
                        $getKey = substr($getKey, 0,strpos($getKey, ':'));
                    }
                    $ret->$getKey = $this->getItems($value);
                }
            }
            return $ret;
        }
        
        if ($type == 'array') {
            $ret = array();
            foreach ($allKeys as $key => $value) {
                $getKey = str_replace($keys.':', '', $value);                
                if (strpos($getKey, ':') !== false) {
                } else {
                    if (strpos($getKey, ':') !== false) {
                        $getKey = substr($getKey, 0,strpos($getKey, ':'));
                    }
                    $val= $this->getItems($value);
                    if (!is_null($val)) {
                        $ret[$getKey] = $val;
                    }
                }
            }
            return $ret;
        }
        
        return false;
    }
    
    private function deleteItems($keys,$root = false)
    {
        if ($root) {
            $scankey = ($keys == '') ? self::PREFX.'*':self::PREFX.$keys.':*';
            $keys = ($keys == '') ? self::PREFX:self::PREFX.$keys;
        }else {
            $scankey = ($keys == '') ? '*':$keys.':*';
        }

        $allKeys = $this->redis->keys($scankey);
        $this->redis->delete($keys);
        
        foreach ($allKeys as $value) {
            $this->redis->delete($value);
        }
        
        return true;
    }
    
    public function get()
    {
        $this->initialize();
        $uri = substr($this->request->getURI(), 1);
        $key ='';
        if ($uri) {
            $hasSubkey = strpos($uri,'/',1);
            $key = ($hasSubkey) ? str_replace('/', ':', $uri):$uri;
        }
        $ret = $this->getItems($key,true);
        if ($ret) {
           $this->response
                ->setStatusCode(200, 'OK')
                ->setJsonContent($ret,true)
                ->send();
        } else {
           $this->response
                ->setStatusCode(404, 'Not Found')
                ->send(); 
        }
    }
    
    
    private function setItems($keys,$values,$root = false)
    {
        try {
            $type = gettype($values);
            if (in_array($type, array('array','object'))){
                if ($root == true) {
                    foreach ($values as $key => $value) {
                        //mark type
                        $itemType = gettype($value);                    
                        $subKey = (($keys == '') ? $key:$keys.':'.$key);
                        $this->redis->set(self::PREFX.$subKey,$itemType);
                        $this->setItems(self::PREFX.$subKey, $value);
                    }
                } else {
                    foreach ($values as $key => $value) {
                        //mark type
                        $itemType = gettype($value);                    
                        $subKey = (($keys == '') ? $key:$keys.':'.$key);
                        $this->redis->set($subKey,$itemType);                        
                        $this->setItems($subKey, $value);
                    }
                }
                
            }else{
                if ($root == true) {
                    $this->redis->set(self::PREFX.$keys,$values);
                } else {
                    $this->redis->set($keys,$values);
                }
                
            }
        } catch (\Exception $ex) {
        }
        
    }
    
    public function create()
    {
        $this->initialize();
        $uri = substr($this->request->getURI(), 1);
        $key ='';
        if ($uri) {
            $hasSubkey = strpos($uri,'/',1);
            $key = ($hasSubkey) ? str_replace('/', ':', $uri):$uri;
        }
        $value = json_decode($this->request->getRawBody());
        $this->setItems($key, $value,true);
        $this->response
            ->setStatusCode(200, 'OK')
            ->send();
    }
    
    
    public function delete()
    {
        $this->initialize();
        $uri = substr($this->request->getURI(), 1);
        $key ='';
        if ($uri) {
            $hasSubkey = strpos($uri,'/',1);
            $key = ($hasSubkey) ? str_replace('/', ':', $uri):$uri;
        }        
        $ret = $this->deleteItems($key,true);
        if ($ret) {
            $this->response
                ->setStatusCode(204, 'OK')
                ->send();
        } else {
            throw new \Exception("delete error!");
        }
    }
}
