<?php

require_once 'Tools.php';

class Xodx_ResourceController extends Xodx_Controller
{
    /**
     *
     * indexAction decides to show a html or a serialized
     * view of a resource if no action is given
     * @param unknown_type $template
     */
    public function indexAction($template)
    {
        $bootstrap = $this->_app->getBootstrap();
        $request = $bootstrap->getResource('request');
        $objectId = $request->getValue('id', 'get');
        $header = $request->getHeader();
        $accept = explode(',',$header['Accept']);
        header('HTTP/1.1 302 Found');

        $template->disableLayout();
        $template->setRawContent('');
        foreach ($accept as $contentType) {
            $contentType = explode(';', $contentType);
            if (stristr($contentType[0], 'html')) {
                //redirect to show action
                break;
            } else if (stristr($contentType[0], 'rdf')) {
                //redirect to rdf action
                header('Location: ' . $this->_app->getBaseUri() . '?c=resource&a=rdf&id=' . $objectId . '&format=rdfxml');
                return $template;
            } else if (stristr($contentType[0], 'turtle')) {
                header('Location: ' . $this->_app->getBaseUri() . '?c=resource&a=rdf&id=' . $objectId . '&format=turtle');
                return $template;
            } else if (stristr($contentType[0], 'image')) {
                header('Location: ' . $this->_app->getBaseUri() . '?c=resource&a=img&id=' . $objectId);
                return $template;
            }
        }
        header('Location: ' . $this->_app->getBaseUri() . '?c=resource&a=show&id=' . $objectId);
        return $template;
    }
    /**
     *
     * Method returns all tuples of a resource to html template
     * @param $template
     */
    public function showAction($template)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');
        $request = $bootstrap->getResource('request');

        $objectId = $request->getValue('id', 'get');
        $objectUri = $this->_app->getBaseUri() . '?c=resource&id=' . $objectId;

        $query = '' .
            'SELECT ?p ?o ' .
            'WHERE { ' .
            '   <' . $objectUri . '> ?p ?o . ' .
            '} ';
        $properties = $model->sparqlQuery($query);

        $activityController = $this->_app->getController('Xodx_ActivityController');
        $activities = $activityController->getActivities($objectUri);

        $template->addContent('templates/resourceshow.phtml');
        $template->properties = $properties;
        $template->activities = $activities;
        // TODO getActivity with objectURI from Xodx_ActivityController
        /**$personController = new Xodx_PersonController($this->_app);
        $activities = $personController->getActivities($personUri);
        $news = $personController->getNotifications($personUri);
        */

        return $template;
    }

    /**
     *
     * rdfAction returns a serialized view of a resource according to content type
     * (default is turtle)
     * @param unknown_type $template
     */
    public function rdfAction ($template)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');
        $request = $bootstrap->getResource('request');

        $objectId = $request->getValue('id', 'get');
        $format = $request->getValue('format', 'get');
        $objectUri = $this->_app->getBaseUri() . '?c=resource&id=' . $objectId;

        $filename = '';
        switch ($format) {
            case 'rdfxml':
                $contentType = 'application/rdf+xml';
                $filename .= '.rdf';
                break;
            case 'rdfn3':
                $contentType = 'text/rdf+n3';
                $filename .= '.n3';
                break;
            case 'rdfjson':
                $contentType = 'application/json';
                $filename .= '.json';
                break;
            case 'turtle':
                $contentType = 'application/x-turtle';
                $filename .= '.ttl';
                break;
            default:
                $contentType = 'application/x-turtle';
                $filename .= '.ttl';
        }

        $modelUri = $model->getModelIri();
        $format = Erfurt_Syntax_RdfSerializer::normalizeFormat($format);
        $serializer = Erfurt_Syntax_RdfSerializer::rdfSerializerWithFormat($format);
        $rdfData = $serializer->serializeResourceToString($objectUri, $modelUri, false, true, array());
        header('Content-type: ' . $contentType);

        $template->disableLayout();
        $template->setRawContent($rdfData);

        return $template;

    }

    /**
     *
     * rdfAction returns a serialized view of a resource according to content type
     * (default is turtle)
     * @param unknown_type $template
     */
    public function imgAction ($template)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');
        $request = $bootstrap->getResource('request');

        $objectId = $request->getValue('id', 'get');
        $objectUri = $this->_app->getBaseUri() . '?c=resource&id=' . $objectId;

        $query = '' .
            'PREFIX foaf: <http://xmlns.com/foaf/0.1/> ' .
            'PREFIX ov: <http://open.vocab.org/docs/> ' .
            'SELECT ?mime ' .
            'WHERE { ' .
            '   <' . $objectUri . '> a foaf:Image ; ' .
            '   ov:hasContentType    ?mime . ' .
            '} ';
        $properties = $model->sparqlQuery($query);
        $mediaController = $this->_app->getController('Xodx_MediaController');

        $template->disableLayout();
        $template->setRawContent('');

        $mimeType = $properties[0]['mime'];

        $mediaController->getImage($objectId, $mimeType);
        //$template->addContent('templates/resourceshow.phtml');
        return $template;
    }

    /**
     *
     * get the type of a ressource
     * @param $resourceUri a URI of a ressource
     */
    public function getType ($resourceUri)
    {
        $bootstrap = $this->_app->getBootstrap();
        $model = $bootstrap->getResource('model');

        $query = '' .
            'SELECT ?type ' .
            'WHERE { ' .
            ' <' . $resourceUri . '> a  ?type  .} ';

        $type = $model->sparqlQuery($query);
        //TODO get linked data if resource is not in out namespace

        return $type[0]['type'];
    }
}