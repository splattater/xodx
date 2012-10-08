<?php

class Xodx_FeedController extends Xodx_Controller
{

    /**
     * Returns a Feed in the spezified format (html, rss, atom)
     */
    public function getFeedAction($template, $uri = null, $format = null)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');
        $request = $bootstrap->getResource('request');

        $nsSioc = 'http://rdfs.org/sioc/ns#';
        $nsFoaf = 'http://xmlns.com/foaf/0.1/';
        $nsAair = 'http://xmlns.notu.be/aair#';

        $uri = $request->getValue('uri');
        $format = $request->getValue('format');

        if ($uri !== null) {
            //TODO change to ActivityController, get activities of things != person
            $activityController = $this->_app->getController('Xodx_ActivityController');
            $activities = $activityController->getActivities($uri);

            $pushController = $this->_app->getController('Xodx_PushController');

            $feedUri = $this->_app->getBaseUri() . '?c=feed&a=getFeed&uri=' . urlencode($uri);

            $updated = '0';

            foreach ($activities as $activity) {
                if (0 > strcmp($updated, $activity['pubDate'])) {
                    $updated = $activity['pubDate'];
                }
            }

            $resourceController = $this->_app->getController('Xodx_ResourceController');
            $type = $resourceController->getType($uri);

            $nameHelper = new Xodx_NameHelper($this->_app);

            $template->setLayout('templates/feed.phtml');
            $template->updated = $updated;
            $template->uri = $uri;
            $template->feedUri = $feedUri;
            $template->hub = $pushController->getDefaultHubUrl();
            $isPerson = false;
            if (($type == $nsSioc . 'Comment') || ($type == $nsFoaf . 'Document') ||
            ($type == $nsFoaf . 'Image') || ($type == $nsAair . 'Activity'))
            {
                $name = 'Test';
            } else {
                $name = $nameHelper->getName($uri);
            }
            $template->name = $name;
            $template->activities = $activities;
        } else {
            // No URI given
        }

        return $template;
    }

    /**
     * This method reads feed data and extracts the specified activities in order to insert or
     * update them in the model
     */
    public function feedToActivity ($feedUri)
    {
        /*$nsAtom = 'http://www.w3.org/2005/Atom';
         $nsAair = 'http://activitystrea.ms/schema/1.0/';

         $xml = simplexml_load_string($feedData);

         $atom = $xml->children($nsAtom);
         $aair = $xml->children($nsAair);

         if (count($atom) < 1 && count($aair) < 1) {
         throw new Exception('Feed is empty');
         } else {
         $activities = array();
         foreach ($atom->entry as $entry) {
         // getActivitystrea.ms namespace
         $entryAtom = $entry->children($nsAtom);
         $entryAair = $entry->children($nsAair);

         $date = (string) $entryAtom->published;

         $actorNode = $entryAtom->author;
         $actorAtom = $actorNode->children($nsAtom);
         $actorUri = (string) $actorAtom->uri;

         $verbUri = (string) $entryAair->verb;

         $objectNode = $entryAair->object;
         $objectAtom = $objectNode->children($nsAtom);
         $objectUri = (string) $objectAtom->id;

         // TODO create new Activity with the data specified in the entry
         $activities[] = new Activity(null, $actorUri, $verbUri, $objectUri, $date);
         }
         }

         $activityController = $this->_app->getController('Xodx_ActivityController');

         $activityController->addActivities($activities);**/
        // load an external feed and display activitytitles
        $Feed = DSSN_Activity_Feed_Factory::newFromUrl($feedUri);
        $lastPubDate = $this->_lastPubDate($feedUri);
        $activityController = $this->_app->getController('Xodx_ActivityController');
        $maxDate = '0';
        $nsXodx = 'http://xodx.org/ns#';
        $nsXsd = 'http://www.w3.org/2001/XMLSchema#';

        foreach ($Feed->getActivities() as $key => $activity) {
            // Only get entries which are newer than last push date
            if ($activity->getPublished > $lastPushed) {
                $date = $activity->getPublished;
                $maxDate = $date;
                $title = $activity->getTitle();
                $activityActor = $activity->getActor();
                $activityVerb = $activity->getVerb();
                $activityObject = $activity->getObject();
                $activity[] = new Activity(null, $actorUri, $verbUri, $objectUri, $date);
                $activityController->addActivities($acivity);
                if ($date > $maxDate) {
                    $maxDate = $date;
                }
            }
        }

        // Update Store with new date of last Push of this Feed
        if ($maxDate > $lastPubDate) {
            $callbackStatement = array(
                $feedData => array(
                    $nsXodx . 'lastPubDate' => array(
                        array(
                            'type' => 'literal',
                            'value' => $maxDate,
                            'datatype' => $nsXsd . 'dateTime'
                        )
                    )
                )
            );
        }
        $store->addMultipleStatements($graphUri, $subscribeStatement);
    }

    /**
     *
     * Method returns the latest date a feed was pushed
     * @param $feedUri an URI of an Activity Feed
     */
    private function _lastPubDate ($feedUri)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');

        $query = '' .
            'PREFIX xodx: <http://example.org/voc/xodx/> ' .
            'SELECT ?date { ' .
            '   <' . $feedUri . '> xodx:lastPubDate ?date . ' .
            '} ' .
            'ORDER BY DESC(?date) LIMIT 1 ' ;
        $maxDate = $model->sparqlQuery($query);

        return $maxDate[0]['date'];
    }
}
