<?php

class Xodx_ActivityController extends Xodx_Controller
{
    public function addactivityAction ($template)
    {
        $bootstrap = $this->_app->getBootstrap();

        $request = $bootstrap->getResource('request');
        $actorUri = $request->getValue('actor', 'post');
        $verb = $request->getValue('verb', 'post');
        $actType = $request->getValue('type', 'post');
        $actContent = $request->getValue('content', 'post');

                $nsAair = 'http://xmlns.notu.be/aair#';

        switch (strtolower($verb)) {
            case 'post':
                $verbUri = $nsAair . $verb;
                break;
            case 'share':
                $verbUri = $nsAair . $verb;
                break;
        }

        switch ($actType) {
            case 'Note';
                $object = array(
                    'type' => $actType,
                    'content' => $actContent,
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
            case 'Bookmark';
                $object = array(
                    'type' => $actType,
                    'about' => $request->getValue('about', 'post'),
                    'content' => $actContent,
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
            case 'Photo';
                $fieldName = 'content';
                $mediaController = new Xodx_MediaController($this->_app);
                $fileInfo = $mediaController->uploadImage($fieldName);
                $object = array(
                    'type' => $actType,
                    'about' => $request->getValue('about', 'post'),
                    'content' => $actContent,
                    'fileName' => $fileInfo['fileId'],
                    'mimeType' => $fileInfo['mimeType'],
                );
                $debugStr = $this->addActivity($actorUri, $verbUri, $object);
            break;
        }
        var_dump($object);
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
        $config = $bootstrap->getResource('config');
        $graphUri = $model->getModelIri();
        $nsXsd = 'http://www.w3.org/2001/XMLSchema#';'PREFIX foaf <http://xmlns.com/foaf/spec/#> ' .
        $nsRdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $nsSioc = 'http://rdfs.org/sioc/ns#';
        $nsAtom = 'http://www.w3.org/2005/Atom/';
        $nsAair = 'http://xmlns.notu.be/aair#';
        $nsXodx = 'http://xodx.org/ns#';
        $nsFoaf = 'http://xmlns.com/foaf/spec/#';
        $nsOv = 'http://open.vocab.org/docs/';

        $activityUri = $this->_app->getBaseUri() . '?c=resource&id=' . md5(rand());
        $now = date('c');
        // Take photo's filename as objectname
        if ($object['type'] == 'Photo') {
            $object['type'] = $nsFoaf . 'Image';
            $type = 'Photo';
            $objectId = $object['fileName'];
            $objectUri = $this->_app->getBaseUri() . '?c=resource&id=' .	$objectId;
        } else if ($object['type'] == 'Bookmark') {
            $object['type'] = $nsFoaf . 'Document';
            $type = 'Bookmark';
            $objectId = md5(rand());
            $objectUri = $this->_app->getBaseUri() . '?c=resource&id=' . $objectId;
        } else if ($object['type'] == 'Note') {
            $object['type'] = $nsFoaf . 'Document';
            $type = 'Note';
            $objectId = md5(rand());
            $objectUri = $this->_app->getBaseUri() . '?c=resource&id=' . $objectId;
        }
        var_dump($object);
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
                )
            );
            // Triples of photo object
            if ($type == 'Photo') {
                $activity[$objectUri][$nsFoaf . 'Image'] = array(
                    array(
                        'type' => 'literal',
                        'value' => $object['fileName'],
                    )
                );
                $activity[$objectUri][$nsOv . 'hasContentType'] = array(
                    array(
                        'type' => 'literal',
                        'value' => $object['mimeType'],
                    )
                );
            }
        // Triples of Bookmark object
            if ($type == 'Bookmark') {
                $activity[$objectUri][$nsAair . 'targetURL'][0]['type'] = 'literal';
                $activity[$objectUri][$nsAair . 'targetURL'][0]['value'] = $object['content'];
            }
            // Adding user text about photo/bookmark
            if (
                ($type == 'Photo' || $type == 'Bookmark') &&
                !empty($object['about'])
            ) {
                $activity[$objectUri][$nsFoaf . 'topic'][0]['type'] = 'literal';
                $activity[$objectUri][$nsFoaf . 'topic'][0]['value'] = $object['about'];
            } else {
                $activity[$objectUri][$nsFoaf . 'topic'][0]['type'] = 'literal';
                $activity[$objectUri][$nsFoaf . 'topic'][0]['value'] = $actContent;
            }

        $store->addMultipleStatements($graphUri, $activity);

        $feedUri = $this->_app->getBaseUri() . '?c=feed&a=getFeed&uri=' . urlencode($actorUri);

        if ($config['push.enable'] == true) {
            $pushController = $this->_app->getController('Xodx_PushController');
            $pushController->publish($feedUri);
        }

        // Subscribe user to feed of activityObject (photo, post, note)
        $feedUri = $this->_app->getBaseUri() . '?c=feed&a=getFeed&uri=' . urlencode($objectUri);
        echo '$feedUri: ' . $feedUri;
        echo '$actorUri: ' . $actorUri;
        $userController = $this->_app->getController('Xodx_UserController');
        $actorUri = urldecode($actorUri);

        $userController->subscribeToFeed($actorUri, $feedUri);

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


            if ($verbUri == $nsAair . 'Post' || $verbUri == $nsAair . 'Share') {
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
        var_dump($activities);
        return $activities;
    }
}
