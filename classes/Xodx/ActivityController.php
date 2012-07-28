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
            case 'http://xmlns.notu.be/aair#Bookmark';
                $object = array(
                    'type' => $actTypeUri,
                    'about' => $request->getValue('about', 'post'),
                    'content' => $actContent,
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
            case 'http://xmlns.notu.be/aair#Photo';
                $fieldName = 'content';
                $mediaController = new Xodx_MediaController($this->_app);
                $fileInfo = $mediaController->uploadImage($fieldName);
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
            	$objectId = $object['fileName'];
                $objectUri = 'http://xodx/object/' .	$objectId;
            } else {
            	$objectId = md5(rand());
                $objectUri = 'http://xodx/object/' . $objectId;
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
            // Triples of photo object
            if ($object['type'] == $nsAair . 'Photo') {
	            $activity[$objectUri][$nsAair . 'largerImage'][0]['type'] = 'uri';
	            $activity[$objectUri][$nsAair . 'largerImage'][0]['value'] =
	            	$this->_app->getBaseUri() . $object['fileName'];	                        	
	            $activity[$objectUri][$nsSioc . 'mimeType'][0]['type'] = 'uri';
	            $activity[$objectUri][$nsSioc . 'mimeType'][0]['value'] = $object['mimeType'];
            }
        // Triples of Bookmark object
            if ($object['type'] == $nsAair . 'Bookmark') {
				$activitiy[$objectUri][$nsAair . 'targetURL'][0]['type'] = 'literal';
				$activitiy[$objectUri][$nsAair . 'targetURL'][0]['value'] =
					$this->_app->getBaseUri() . $object['content'];
            }	
            // Adding user text about photo/bookmark
            if (
                ($object['type'] == $nsAair . 'Photo' || $object['type'] == $nsAair . 'Bookmark') &&
                !empty($object['about'])
            ) {
	            $activity[$objectUri][$nsAair . 'content'][0]['type'] = 'literal';
	            $activity[$objectUri][$nsAair . 'content'][0]['value'] = $object['about'];            	
            }

        $store->addMultipleStatements($graphUri, $activity);

        $pushController = $this->_app->getController('Xodx_PushController');
        $feedUri = $this->_app->getBaseUri() . '?c=feed&a=getFeed&uri=' . urlencode($actorUri);

        $pushController->publish($feedUri);

        return $feedUri . "\n" . var_export($activity, true);
        }
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
	 * @param $personUri the uri of the person whoes activities should be returned
     * @return an array of activities
     * TODO return an array of Xodx_Activity objects
     * TODO getActivity by objectURI
     */
    public function getActivities ($personUri)
    {
        // There are two namespaces, one is used in atom files the other one for RDF
        $nsAairAtom = 'http://activitystrea.ms/schema/1.0/';
        $nsAair = 'http://xmlns.notu.be/aair#';

        $model = $this->_app->getBootstrap()->getResource('model');

        if ($personUri === null) {
            return null;
        }

        $query = '' .
            'PREFIX atom: <http://www.w3.org/2005/Atom/> ' .
            'PREFIX aair: <http://xmlns.notu.be/aair#> ' .
            'SELECT ?activity ?date ?verb ?object ' .
            'WHERE { ' .
            '   ?activity a                   aair:Activity ; ' .
            '             aair:activityActor  <' . $personUri . '> ; ' .
            '             atom:published      ?date ; ' .
            '             aair:activityVerb   ?verb ; ' .
            '             aair:activityObject ?object . ' .
            '} ' .
            'ORDER BY DESC(?date)';
        $activitiesResult = $model->sparqlQuery($query);

        $activities = array();

        foreach ($activitiesResult as $activity) {
            $activityUri = $activity['activity'];
            $verbUri = $activity['verb'];
            $objectUri = $activity['object'];

            $activity['date'] = self::_issueE24fix($activity['date']);

            $activity = array(
                'title' => '"' . $personUri . '" did "' . $activity['verb'] . '".',
                'uri' => $activityUri,
                'author' => 'Natanael',
                'authorUri' => $personUri,
                'pubDate' => $activity['date'],
                'verb' => $activity['verb'],
                'object' => $activity['object'],
            );

            if ($verbUri == $nsAair . 'Post') {
                $objectResult = $model->sparqlQuery(
                    'PREFIX atom: <http://www.w3.org/2005/Atom/> ' .
                    'PREFIX aair: <http://xmlns.notu.be/aair#> ' .
                    'PREFIX sioc: <http://rdfs.org/sioc/ns#> ' .
                    'SELECT ?type ?content ?date ' .
                    'WHERE { ' .
                    '   <' . $objectUri . '> a ?type ; ' .
                    '        sioc:created_at ?date ; ' .
                    '        aair:content ?content . ' .
                    '} '
                );

                if (count($objectResult) > 0) {
                    $activity['objectType'] = $objectResult[0]['type'];
                    $activity['objectPubDate'] = self::_issueE24fix($objectResult[0]['date']);
                    $activity['objectContent'] = $objectResult[0]['content'];
                }
            } else {
            }

            $activities[] = $activity;
        }

        return $activities;
    }
}
