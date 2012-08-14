<?php

require_once 'Tools.php';

class Xodx_ResourceController extends Xodx_Controller
{
    public function showAction($template)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');
        $request = $bootstrap->getResource('request');

        $objectUri = $this->_app->getBaseUri() . '?c=resource&a=show&objectId=' . $request->getValue('objectId', 'get');

        $nsFoaf = 'http://xmlns.com/foaf/0.1/';
        $nsAair = 'http://xmlns.notu.be/aair#';
        $nsXodx = 'http://xodx.org/ns#';

        $objectQuery = 'PREFIX aair: <' . $nsAair. '> ' . 
        'PREFIX xodx: <' . $nsXodx. '> ' .
        'SELECT ?type ?content ?image ?link ?mimeType ' . 
        'WHERE { ' .
            '   <' . $objectUri . '> a ?type . ' .
            '   OPTIONAL {<' . $objectUri . '> aair:largerImage ?image .} ' .
            '   OPTIONAL {<' . $objectUri . '> aair:type ?type .} ' .
            '   OPTIONAL {<' . $objectUri . '> aair:content ?content .} ' .
            '   OPTIONAL {<' . $objectUri . '> aair:targetURL ?link .} ' .
            '   OPTIONAL {<' . $objectUri . '> aair:mimeType ?mimeType .} ' .
            '}';
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
                    'mimeType' => $modelNew->getValue($objectUri, $nsAair . 'mimeType'),
                    'link' => $modelNew->getValue($objectUri, $nsAair . 'link'),
                );
            }
        }
        // TODO getActivity with objectURI from Xodx_ActivityController
        /**$personController = new Xodx_PersonController($this->_app);
        $activities = $personController->getActivities($personUri);
        $news = $personController->getNotifications($personUri);
        */
        $template->resourceshowObjectUri = $objectUri;
        if (!empty($object[0]['image']))
            $template->resourceshowImage = $object[0]['image'];
        if (!empty($object[0]['type']))
            $template->resourceshowType = $object[0]['type'];
        if (!empty($object[0]['content']))
            $template->resourceshowContent = $object[0]['content'];
        if (!empty($object[0]['content']))
            $template->resourceshowContent = $object[0]['content'];
        $template->addContent('templates/resourceshow.phtml');

        return $template;
    }
}
