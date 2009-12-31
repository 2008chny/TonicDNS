<?php

require_once('../lib/tonic.php');

class RequestTester extends UnitTestCase {
    
    function testRequestURI() {
        
        $config = array(
            'uri' => '/requesttest/one/two'
        );
        
        $request = new Request($config);
        
        $this->assertEqual($request->uri, $config['uri']);
        
    }
    
    function testGetRequestMethod() {
        
        $config = array();
        
        $request = new Request($config);
        
        $this->assertEqual($request->method, 'GET');
        
    }
    
    function testPutRequestMethodWithData() {
        
        $config = array(
            'method' => 'put',
            'data' => 'some data'
        );
        
        $request = new Request($config);
        
        $this->assertEqual($request->method, 'PUT');
        $this->assertEqual($request->data, 'some data');
        
    }
    
    function testConnegOnBareURI() {
        
        $config = array(
            'uri' => '/requesttest/one/two',
            'accept' => '',
            'acceptLang' => ''
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two'
        ));
        $this->assertEqual($request->uri, '/requesttest/one/two');
    
    }
    
    function testConnegOnExtensionURI() {
        
        $config = array(
            'uri' => '/requesttest/one/two.html',
            'accept' => '',
            'acceptLang' => ''
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.html',
            '/requesttest/one/two'
        ));
        $this->assertEqual($request->uri, '/requesttest/one/two');
        
    }
    
    function testConnegOnBareURIWithAccept() {
        
        $config = array(
            'uri' => '/requesttest/one/two',
            'accept' => 'image/png;q=0.5,text/html',
            'acceptLang' => ''
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.html',
            '/requesttest/one/two.png',
            '/requesttest/one/two'
        ));
        
    }
    
    function testConnegOnExtensionURIWithAccept() {
        
        $config = array(
            'uri' => '/requesttest/one/two.html',
            'accept' => 'image/png;q=0.5,text/html',
            'acceptLang' => ''
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.html',
            '/requesttest/one/two.png.html',
            '/requesttest/one/two.png',
            '/requesttest/one/two'
        ));
        
    }
    
    function testConnegOnBareURIWithAcceptLang() {
        
        $config = array(
            'uri' => '/requesttest/one/two',
            'accept' => '',
            'acceptLang' => 'fr;q=0.5,en'
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.en',
            '/requesttest/one/two.fr',
            '/requesttest/one/two'
        ));
        
    }
    
    function testConnegOnExtensionURIWithAcceptLang() {
        
        $config = array(
            'uri' => '/requesttest/one/two.html',
            'accept' => '',
            'acceptLang' => 'fr;q=0.5,en'
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.html.en',
            '/requesttest/one/two.html.fr',
            '/requesttest/one/two.html',
            '/requesttest/one/two.en',
            '/requesttest/one/two.fr',
            '/requesttest/one/two'
        ));
        
    }
    
    function testConnegOnBareURIWithAcceptAndAcceptLang() {
        
        $config = array(
            'uri' => '/requesttest/one/two',
            'accept' => 'image/png;q=0.5,text/html',
            'acceptLang' => 'fr;q=0.5,en'
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.html.en',
            '/requesttest/one/two.html.fr',
            '/requesttest/one/two.html',
            '/requesttest/one/two.png.en',
            '/requesttest/one/two.png.fr',
            '/requesttest/one/two.png',
            '/requesttest/one/two.en',
            '/requesttest/one/two.fr',
            '/requesttest/one/two'
        ));
        
    }
    
    function testConnegOnExtensionURIWithAcceptAndAcceptLang() {
        
        $config = array(
            'uri' => '/requesttest/one/two.html',
            'accept' => 'image/png;q=0.5,text/html',
            'acceptLang' => 'fr;q=0.5,en'
        );
        
        $request = new Request($config);
        $this->assertEqual($request->uris, array(
            '/requesttest/one/two.html.en',
            '/requesttest/one/two.html.fr',
            '/requesttest/one/two.html',
            '/requesttest/one/two.png.html',
            '/requesttest/one/two.png.en',
            '/requesttest/one/two.png.fr',
            '/requesttest/one/two.png',
            '/requesttest/one/two.en',
            '/requesttest/one/two.fr',
            '/requesttest/one/two'
        ));
        
    }
    
    function testResourceLoaderWithNoResources() {
        
        $config = array(
            'uri' => '/three'
        );
        
        $request = new Request($config);
        $resource = $request->loadResource();
        
        $this->assertEqual(get_class($resource), 'NoResource');
        
    }
    
    function testResourceLoaderWithAResources() {
        
        $config = array(
            'uri' => '/requesttest/one'
        );
        
        $request = new Request($config);
        $resource = $request->loadResource();
        
        $this->assertEqual(get_class($resource), 'NewResource');
        
    }
    
    function testResourceLoaderWithAChildResources() {
        
        $config = array(
            'uri' => '/requesttest/one/two'
        );
        
        $request = new Request($config);
        $resource = $request->loadResource();
        
        $this->assertEqual(get_class($resource), 'ChildResource');
        
    }
    
    function testResourceLoaderWithRegexURIMatch() {
        
        $config = array(
            'uri' => '/requesttest/three/something/four'
        );
        
        $request = new Request($config);
        $resource = $request->loadResource();
        
        $this->assertEqual(get_class($resource), 'NewResource');
        
    }
    
}


/* Test resource definitions */

/**
 * @uri /requesttest/one
 * @uri /requesttest/three/.+/four 12
 */
class NewResource extends Resource {

}

/**
 * @uri /requesttest/one/two
 */
class ChildResource extends NewResource {

}


?>
