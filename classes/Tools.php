<?php

class Tools
{
    /**
     * @warning This function sends a web request and might take a long time
     * @hint You should run this function asynchrounusly or independent of your UI
     */
    public static function getLinkedDataResource($uri)
    {
        $app = Application::getInstance();
        $model = $app->getBootstrap()->getResource('Model');
        $modelUri = $model->getModelIri();

        $r = new Erfurt_Rdf_Resource($uri);

        // Try to instanciate the requested wrapper
        //$wrapper = new LinkeddataWrapper();
        $wrapperName = 'Linkeddata';
        $wrapper = Erfurt_Wrapper_Registry::getInstance()->getWrapperInstance($wrapperName);

        $wrapperResult = null;
        $wrapperResult = $wrapper->run($r, $modelUri, true);

        $newStatements = null;
        if ($wrapperResult === false) {
            // IMPORT_WRAPPER_NOT_AVAILABLE;
        } else if (is_array($wrapperResult)) {
            $newStatements = $wrapperResult['add'];
            // TODO make sure to only import the specified resource
            $newModel = new Erfurt_Rdf_MemoryModel($newStatements);
            $newStatements = array();
            $newStatements[$uri] = $newModel->getPO($uri);
        } else {
            // IMPORT_WRAPPER_ERR;
        }

        return $newStatements;
    }

    /**
    * Matches an array of mime types against the Accept header in a request.
    *
    * @param Xodx_Request $request the request
    * @param array $supportedMimetypes The mime types to match against
    * @return string
    */
    public static function matchMimetypeFromRequest(
        Xodx_Request $request,
        array $supportedMimetypes
    )
    {
        // get accept header
        $header = $request->getHeader();
        $acceptHeader = strtolower($header['Accept']);

        require_once 'Mimeparse.php';
        try {
            $match = @Mimeparse::best_match($supportedMimetypes, $acceptHeader);
        } catch (Exception $e) {
            $match = '';
        }

        return $match;
    }
}
