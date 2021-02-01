<?php namespace Phippen\Eoriginal;

use GuzzleHttp\Client;

class Eoriginal
{
    protected $login;
    protected $baseUri;
    protected $client;

    public function __construct($config)
    {
        $this->login = array_except($config,['baseUri']);
        $this->baseUri = $config['baseUri'];
        $this->client = new Client(['base_uri' => $config['baseUri'], 'cookies' => true]);
        $this->login();
    }

    protected function login()
    {
        return $this->get('eoLogin', $this->login, true);
    }
    
    public function createTransaction($properties)
    {
        return $this->get('eoCreateTransaction', $this->rename($properties))['transactionList']['transaction'];
    }

    public function deleteTransaction($transactionSid)
    {
        return $this->get('eoDeleteTransaction', compact('transactionSid'));
    }

    public function setTransactionProperties($transactionSid, $properties)
    {
        $properties = array_merge(compact('transactionSid'), $this->rename($properties));
        return $this->get('eoSetTransactionProperties', $properties)['transactionList']['transaction'];
    }

    public function getTransactionProperties($transactionSid)
    {
        return $this->get('eoGetTransactionProperties', compact('transactionSid'));
//        return $this->get('eoGetTransactionProperties', compact('transactionSid'))['transactionList']['transaction'];
    }

    public function searchTransactions($properties)
    {
        // ddMMyyyy HH:mm:ss
        return $this->get('eoSearchTransactions', $this->rename($properties))['transactionList']['transaction'];
    }

    public function searchRequests()
    {
        return $this->get('eoSearchRequests');
    }

    public function reportInventory($properties)
    {
        // ddMMyyyy HH:mm:ss
        return $this->get('eoReportInventory', $this->rename($properties));
    }

    public function getTransactionDocuments($transactionSid, $properties = null)
    {
        return $this->get('eoGetTransactionDocuments', array_merge(compact('transactionSid'), $properties));
    }

    public function createDocumentProfile($transactionSid, $properties = null)
    {
        return $this->get('eoCreateDocumentProfile', array_merge(compact('transactionSid'), $properties));
    }

    public function getDocumentProfileProperties($dpSid)
    {
        return $this->get('eoGetDocumentProfileProperties', compact('dpSid'));
    }

    public function uploadDocument($dpSid, $properties = null)
    {
        return $this->get('eoUploadDocument', array_merge(compact('dpSid'), $properties));
    }
    
    public function searchDocuments($properties)
    {
        return $this->get('eoSearchDocuments', $properties);
    }

    public function uploadInsertFormFields($dpSid, $properties = null)
    {
        return $this->get('eoUploadInsertFormFields', array_merge(compact('dpSid'), $properties));
    }

    public function mergeData($dpSid, $properties, $includeFormFieldsinResponse)
    {
        $doc = new \DomDocument();
        $set = $doc->createElementNS('http://www.eoriginal.com/TransformationInstructionsSet', 'transformationInstructionSet');
        $set->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation',
            'http://www.eoriginal.com/TransformationInstructionsSet http://schemas.eoriginal.com/releases/9.2/transform/transformation-instruction-set.xsd');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:as', 'http://www.eoriginal.com/addSignature');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:atd', 'http://www.eoriginal.com/AddTextDataInstructions');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:empty', 'http://www.eoriginal.com/EmptyInstructions');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:tsv', 'http://www.eoriginal.com/TypeSettingValues');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $content = $doc->createElement('transformationInstructions');
        $content->setAttribute('xsi:type', 'atd:AddTextData');
        $content->setAttribute('name', 'addTextData');
        $list = $doc->createElement('atd:textDataList');
        foreach($properties as $fieldName => $text){
            $data = $doc->createElement('atd:textData');
            $data->appendChild($doc->createElement('atd:fieldName', $fieldName));
            $data->appendChild($doc->createElement('atd:text', $text));
            $list->appendChild($data);
        }
        $content->appendChild($list); $set->appendChild($content); $doc->appendChild($set);
        $request = $this->client->post('/ecore/', [
            'multipart' => [[
                    'name' => 'action',
                    'contents' => 'eoMergeData'
                ], [
                    'name' => 'dpSid',
                    'contents' => $dpSid
                ], [
                    'name' => 'includeFormFieldsinResponse',
                    'contents' => $includeFormFieldsinResponse
                ], [
                    'name' => 'formFieldDataXML',
                    'filename' => 'merged.xml',
                    'contents' => str_replace(PHP_EOL, '', $doc->saveXml()),
                    'headers' => ['Content-Type' => 'text/xml', 'charset' => 'UTF8']
                ],
            ]]);
        $attributes = (new \SimpleXMLElement($request->getBody()->getContents()));
        if(current($attributes->attributes()->{'status'}) == 'error'){
            abort(404, $attributes->children()->errorList->error->message);
        }
        return json_decode(json_encode($attributes->children()->eventResponse), 1);
    }

    public function configureSortOrder($transactionSid, $dpSid)
    {
        $doc = new \DomDocument();
        $set = $doc->createElementNS('http://www.eoriginal.com/ssweb/ConfigureSortOrder', 'eoConfigureSortOrder');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $set->setAttribute('transactionSid', $transactionSid);
        $set->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation',
            'http://www.eoriginal.com/ssweb/ConfigureSortOrder http://schemas.eoriginal.com/releases/9.2/ssweb/configure-sort-order.xsd');
        $content = $doc->createElement('documentList');
        $content->appendChild($doc->createElement('dpSid', $dpSid));
        $set->appendChild($content); $doc->appendChild($set);
        $request = $this->client->post('/ecore/', [
            'multipart' => [[
                'name' => 'action',
                'contents' => 'eoConfigureSortOrder'
            ],[
                'name' => 'instructionsXML',
                'filename' => 'sortOrder.xml',
                'contents' => str_replace(PHP_EOL, '', $doc->saveXml()),
                'headers' => ['Content-Type' => 'text/xml', 'charset' => 'UTF8']
            ]
            ]]);
        $attributes = (new \SimpleXMLElement($request->getBody()->getContents()));
        if(current($attributes->attributes()->{'status'}) == 'error'){
            abort(404, $attributes->children()->errorList->error->message);
        }
        return json_decode(json_encode($attributes->children()->eventResponse), 1);
    }

    public function getAuthCode($transactionSid, $role)
    {
        $doc = new \DomDocument();
        $set = $doc->createElementNS('http://www.eoriginal.com/ssweb/GetAuthCode', 'eoGetAuthCode');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $set->setAttribute('transactionSid', $transactionSid);
        $set->setAttribute('expiresIn', 300);
        $set->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation',
            'http://www.eoriginal.com/ssweb/GetAuthCode http://schemas.eoriginal.com/releases/9.2/ssweb/get-auth-code.xsd');
        $obj = $doc->createElement('role');
        $obj->setAttribute('name', $role['role']);
        $obj->setAttribute('firstName', $role['firstName']);
        $obj->setAttribute('lastName', $role['lastName']);
        $obj->setAttribute('email', $role['email']);
        $obj->setAttribute('idCheck', 'none');
        $obj->setAttribute('consent', 'UNSPECIFIED');
        $set->appendChild($obj); $doc->appendChild($set);
        $request = $this->client->post('/ecore/', [
            'multipart' => [[
                'name' => 'action',
                'contents' => 'eoGetAuthCode'
            ],[
                'name' => 'instructionsXML',
                'filename' => 'role.xml',
                'contents' => str_replace(PHP_EOL, '', $doc->saveXml()),
                'headers' => ['Content-Type' => 'text/xml', 'charset' => 'UTF8']
            ]
            ]]);
        $attributes = (new \SimpleXMLElement($request->getBody()->getContents()));
        if(current($attributes->attributes()->{'status'}) == 'error'){
            abort(404, $attributes->children()->errorList->error->message);
        }
        return json_decode(json_encode($attributes->children()->eventResponse), 1);
    }

    public function getAuthUrl($transactionSid, $role)
    {
        $code = $this->getAuthCode($transactionSid, $role)['authenticationToken'];
        return $this->baseUri.'ssweb/eo_security_check?authCode='.$code;
    }



//    public function configurePath($transactionSid, $dpSid, $roles)
//    {
//        $ddoc = new \DomDocument();
//        $dset = $ddoc->createElementNS('http://www.eoriginal.com/ssweb/ConfigureSortOrder', 'eoConfigureSortOrder');
//        $dset->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
//        $dset->setAttribute('transactionSid', $transactionSid);
//        $dset->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation',
//            'http://www.eoriginal.com/ssweb/ConfigureSortOrder http://schemas.eoriginal.com/releases/9.2/ssweb/configure-sort-order.xsd');
//        $dcontent = $ddoc->createElement('documentList');
//        $dcontent->setAttribute('dpSid', $dpSid);
//        $dset->appendChild($dcontent); $ddoc->appendChild($dset);
//
//        $doc = new \DomDocument();
//        $set = $doc->createElementNS('http://www.eoriginal.com/ssweb/ConfigureRoles', 'eoConfigureRoles');
//        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
//        $set->setAttribute('transactionSid', $transactionSid);
//        $set->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation',
//            'http://www.eoriginal.com/ssweb/ConfigureRoles http://schemas.eoriginal.com/releases/9.2/ssweb/configure-roles.xsd');
//        $content = $doc->createElement('rolesList'); $i = 0;
//        foreach($roles as $role){
//            $item = $doc->createElement('role');
//            $item->setAttribute('name', $role['role']);
//            $item->setAttribute('order', $i);
//            foreach(array_except($role, 'role') as $param => $value){
//                $item->appendChild($doc->createElement($param, $value));
//            }
//            $content->appendChild($item);
//            $i++;
//        }
//        $set->appendChild($content); $doc->appendChild($set);
//        $request = $this->client->post('/ecore/', [
//            'multipart' => [[
//                'name' => 'action',
//                'contents' => 'eoConfigurePath'
//            ],[
//                'name' => 'sortOrderInstructionsXML',
//                'filename' => 'sortOrder.xml',
//                'contents' => str_replace(PHP_EOL, '', $ddoc->saveXml()),
//                'headers' => ['Content-Type' => 'text/xml', 'charset' => 'UTF8']
//            ],[
//                'name' => 'rolesInstructionsXML',
//                'filename' => 'roles.xml',
//                'contents' => str_replace(PHP_EOL, '', $doc->saveXml()),
//                'headers' => ['Content-Type' => 'text/xml', 'charset' => 'UTF8']
//            ],
//            ]]);
//        $attributes = (new \SimpleXMLElement($request->getBody()->getContents()));
//        if(current($attributes->attributes()->{'status'}) == 'error'){
//            abort(404, $attributes->children()->errorList->error->message);
//        }
//        return json_decode(json_encode($attributes->children()->eventResponse), 1);
//    }

    public function configureRoles($transactionSid, $roles)
    {
        $doc = new \DomDocument();
        $set = $doc->createElementNS('http://www.eoriginal.com/ssweb/ConfigureRoles', 'eoConfigureRoles');
        $set->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $set->setAttribute('transactionSid', $transactionSid);
        $set->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation',
            'http://www.eoriginal.com/ssweb/ConfigureRoles http://schemas.eoriginal.com/releases/9.2/ssweb/configure-roles.xsd');
        $content = $doc->createElement('rolesList'); $i = 0;
        foreach($roles as $role){
            $item = $doc->createElement('role');
            $item->setAttribute('name', $role['role']);
            $item->setAttribute('order', $i);
            foreach(array_except($role, 'role') as $param => $value){
                $item->appendChild($doc->createElement($param, $value));
            }
            $content->appendChild($item);
            $i++;
        }
        $set->appendChild($content); $doc->appendChild($set);
        $request = $this->client->post('/ecore/', [
            'multipart' => [[
                'name' => 'action',
                'contents' => 'eoConfigureRoles'
            ], [
                'name' => 'instructionsXML',
                'filename' => 'roles.xml',
                'contents' => str_replace(PHP_EOL, '', $doc->saveXml()),
                'headers' => ['Content-Type' => 'text/xml', 'charset' => 'UTF8']
            ],
            ]]);
        $attributes = (new \SimpleXMLElement($request->getBody()->getContents()));
        if(current($attributes->attributes()->{'status'}) == 'error'){
            abort(404, $attributes->children()->errorList->error->message);
        }
        return json_decode(json_encode($attributes->children()->eventResponse), 1);
    }

    public function getCopy($dpSid, $versionNumber, $watermarkProperties = null, $watermarkName = null)
    {
        return $this->get('eoGetCopy', compact('dpSid', 'versionNumber', 'watermarkProperties', 'watermarkName'))['documentList']['document']['documentData'];
    }
    
    protected function get($action, $query = null, $noContent = null)
    {
        $query = $query ? array_merge(compact('action'), $query) : null;
        $request = $this->client->get('/ecore/', compact('query'));
        $attributes = (new \SimpleXMLElement($request->getBody()->getContents()));
        if(current($attributes->attributes()->{'status'}) == 'error'){
            abort(404, $attributes->children()->errorList->error->message);
        }
        return json_decode(json_encode($attributes->children()->eventResponse), 1);
    }

    private function rename($properties)
    {
        foreach($properties as $key => $value){
            if(in_array($key, [
                'sid',
                'name',
                'description',
                'typeName',
                'businessProcessName',
                'retentionPolicyName',
                'stateName',
                'xRef1',
                'xRef2',
                'xRef3',
                'extraData'
            ])){
                unset($properties[$key]);
                $properties['transaction'.ucfirst($key)] = $value;
            }
        }
        return $properties;
    }
}