<?php

require_once 'Tools.php';

class Xodx_ResourceController extends Xodx_Controller
{
    public function listAction($template)
    {
        $model = $this->_app->getBootstrap()->getResource('Model');

        $profiles = $model->sparqlQuery(
            'PREFIX foaf: <http://xmlns.com/foaf/0.1/> ' . 
            'SELECT ?profile ?person ?name ' . 
            'WHERE { ' .
            '   ?profile a foaf:PersonalProfileDocument . ' .
            '   ?profile foaf:primaryTopic ?person . ' .
            '   ?person foaf:name ?name . ' .
            '}'
        );

        $template->profilelistList = $profiles;
        $template->addContent('templates/profilelist.phtml');

        $template->addDebug(var_export($profiles, true));

        return $template;
    }

    public function showAction($template)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');
        $request = $bootstrap->getResource('request');

        // get URI
        $objectUri = $request->getValue('mediaId', 'get');

        $nsFoaf = 'http://xmlns.com/foaf/0.1/';
        $nsAair = 'http://xmlns.notu.be/aair#';

        $objectQuery = 'PREFIX aair: <' . $nsAair. '> ' . 
            'SELECT ?type ?content ?image ?link ' . 
            'WHERE { ' .
            '   <' . $objectUri . '> a aair:Activity . ' .
            '   OPTIONAL {<' . $objectUri . '> aair:largerImage ?image .} ' .
            '   OPTIONAL {<' . $objectUri . '> aair:type ?type .} ' .
            '   OPTIONAL {<' . $objectUri . '> aair:content ?content .} ' .
        	'   OPTIONAL {<' . $objectUri . '> aair:targetURL ?link .} ' .
            '}';

/**        // TODO deal with language tags
        $contactsQuery = 'PREFIX foaf: <' . $nsFoaf . '> ' . 
            'SELECT ?contactUri ?name ' . 
            'WHERE { ' .
            '   <' . $personUri . '> foaf:knows ?contactUri . ' .
            '   OPTIONAL {?contactUri foaf:name ?name .} ' .
            '}';
*/
        $object = $model->sparqlQuery($objectQuery);

        if (count($object) < 1) {
            $newStatements = Tools::getLinkedDataResource($objectUri);
            if ($newStatements !== null) {
                $template->addDebug('Import Object with LinkedDate');

                $modelNew = new Erfurt_Rdf_MemoryModel($newStatements);
                $newStatements = $modelNew->getStatements();

                $template->addDebug(var_export($newStatements, true));

                $object = array();
                $object[0] = array(
                    'type' => $modelNew->getValue($objectUri, $nsAair . 'type'),
                    'content' => $modelNew->getValue($objectUri, $nsAair . 'content'),
                    'image' => $modelNew->getValue($objectUri, $nsAair . 'image'),
                	'link' => $modelNew->getValue($objectUri, $nsAair . 'link'),
                );
            }
        }
		// TODO getActivity with objectURI from Xodx_ActivityController
        $personController = new Xodx_PersonController($this->_app);
        $activities = $personController->getActivities($personUri);
        $news = $personController->getNotifications($personUri);

        $template->profileshowPersonUri = $personUri;
        $template->resourceshowImage = $object[0]['image'];
        $template->profileshowName = $object[0]['type'];
        $template->profileshowNick = $object[0]['nick'];
        $template->resourceshowActivities = $activities;
        $template->profileshowKnows = $knows;
        $template->profileshowNews = $news;
        $template->addContent('templates/profileshow.phtml');

        return $template;
    }
}
