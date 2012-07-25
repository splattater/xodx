<?php

class Xodx_ActivityController extends Xodx_Controller
{
    public function addactivityAction ($template)
    {
        $bootstrap = $this->_app->getBootstrap();

        $request = $bootstrap->getResource('request');
        $actorUri = $request->getValue('actor', 'post');
        $verbUri = $request->getValue('verb', 'post');
        $actTypeUri = $request->getValue('type', 'post');
        $actContent = $request->getValue('content', 'post');

        switch ($actTypeUri) {
            case 'http://xmlns.notu.be/aair#Note';
                $object = array(
                    'type' => $actTypeUri,
                    'content' => $actContent,
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
            case 'http://xmlns.notu.be/aair#Link';
                $object = array(
                    'type' => $actTypeUri,
                    'about' => $request->getValue('about', 'post'),
                    'content' => $actContent,
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
            case 'http://xmlns.notu.be/aair#Photo';
                $fieldName = 'content';
                $fileInfo = $this->_uploadImage($fieldName);
                $object = array(
                    'type' => $actTypeUri,
                    'about' => $request->getValue('about', 'post'),
                    'content' => $actContent,
                    'fileName' => $fileInfo['fileId'],
                    'mimeType' => $fileInfo['mimeType'],
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
        }
        $template->addDebug($debugStr);

        return $template;
    }

    /**
     * This method adds a new activity to the store
     * TODO should be replaced by a method with takes a Xodx_Activity object
     */
    public function addActivity ($actorUri, $verbUri, $object)
    {

        $bootstrap = $this->_app->getBootstrap();

        $store = $bootstrap->getResource('store');
        $model = $bootstrap->getResource('model');
        $graphUri = $model->getModelIri();

        $nsXsd = 'http://www.w3.org/2001/XMLSchema#';
        $nsRdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $nsSioc = 'http://rdfs.org/sioc/ns#';
        $nsAtom = 'http://www.w3.org/2005/Atom/';
        $nsAair = 'http://xmlns.notu.be/aair#';

        $activityUri = 'http:///xodx/activity/' . md5(rand()) . '/';
        $now = date('c');

        if ($object['type'] == 'uri') {
            $objectUri = $object['value'];
        } else {
            // Take photo's filename as objectname
            if ($object['type'] == $nsAair . 'Photo') {
                $objectUri = 'http://xodx.local/xodx/object/' . $object['fileName'] . '/';
            } else {
                $objectUri = 'http://xodx.local/xodx/object/' . md5(rand()) . '/';
            }
        }

        $activity = array(
            $activityUri => array(
                $nsRdf . 'type' => array(
                    array(
                        'type' => 'uri',
                        'value' => $nsAair . 'Activity'
                    )
                ),
                $nsAtom . 'published' => array(
                    array(
                        'type' => 'literal',
                        'value' => $now,
                        'datatype' => $nsXsd . 'dateTime'
                    )
                ),
                $nsAair . 'activityActor' => array(
                    array(
                        'type' => 'uri',
                        'value' => $actorUri
                    )
                ),
                $nsAair . 'activityVerb' => array(
                    array(
                        'type' => 'uri',
                        'value' => $verbUri
                    )
                ),
                $nsAair . 'activityObject' => array(
                    array(
                        'type' => 'uri',
                        'value' => $objectUri
                    )
                )
            )
        );

        if ($object['type'] != 'uri') {
            $actTypeUri = $object['type'];
            $actContent = $object['content'];

            $activity[$objectUri] = array(
                $nsRdf . 'type' => array(
                    array(
                        'type' => 'uri',
                        'value' => $actTypeUri
                    )
                ),
                $nsSioc . 'created_at' => array(
                    array(
                        'type' => 'literal',
                        'value' => $now,
                        'datatype' => $nsXsd . 'dateTime'
                    )
                ),
                $nsSioc . 'has_creator' => array(
                    array(
                        'type' => 'uri',
                        'value' => $actorUri
                    )
                ),
                $nsAair . 'content' => array(
                    array(
                        'type' => 'literal',
                        'value' => $actContent
                    ),
                )
            );
            if ($object['type'] == $nsAair . 'Photo') {
                $activity[$objectUri] = array(
                    $nsSioc . 'URL' => array(
                        array(
                            'type' => 'uri',
                            'value' => $this->_app->getBaseUri() . $object['fileName']
                        )
                    ),
                    $nsSioc . 'mimeType' => array(
                        array(
                            'type' => 'uri',
                            'value' => $object['mimeType']
                        )
                    ),
                );
            }
        }
        $store->addMultipleStatements($graphUri, $activity);

        $pushController = new Xodx_PushController($this->_app);
        $feedUri = $this->_app->getBaseUri() . '?c=feed&a=getFeed&uri=' . urlencode($actorUri);

        $pushController->publish($feedUri);

        return $feedUri . "\n" . var_export($activity, true);
    }

    /**
     * This method adds multiple activities to the store
     * @param $activities is an array of Xodx_Activity objects
     */
    public function addActivities (array $activities)
    {
        $bootstrap = $this->_app->getBootstrap();

        $store = $bootstrap->getResource('store');
        $model = $bootstrap->getResource('model');
        $graphUri = $model->getModelIri();

        foreach ($activities as $activity) {
            $store->addMultipleStatements($graphUri, $activity->toGraphArray());
        }
    }

    /**
    * This method uploads an image file after using an upload form
    * @param $fileName the name.ext of the file posted
    * @return Array with 'fileId' and 'mimeType'
    */
    private function _uploadImage($fieldName)
    {
        $uploadDir = '/var/www/xodx/raw/';
        $checkFile = basename($_FILES[$fieldName]['name']);
        $pathParts = pathinfo($checkFile);

        // Check if file's MIME-Type is an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $checkType = finfo_file($finfo, $_FILES[$fieldName]['tmp_name']);
        $allowedTypes = array(
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/tiff',
            'image/x-ms-bmp',
            'image/x-bmp',
            'image/bmp',
        );

        if (!in_array($checkType, $allowedTypes)) {
            throw new Exception('Unsupported MIME-Type: ' . $checkType);
            return false;
        }

        $uploadFile = md5(rand());
        $uploadPath = $uploadDir . $uploadFile;

        // Upload File
        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadPath)) {
            $return = array (
                'fileId' => $uploadFile,
                'mimeType' => $checkType
            );
            return $return;
        } else {
            throw new Exception('Could not move uploaded file to upload directory: ' . $uploadPath);
        }
    }
}
