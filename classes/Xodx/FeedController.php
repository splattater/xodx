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

            if (
                $type == $nsSioc . 'Comment'
                || $type == $nsFoaf . 'Document'
                || $type == $nsFoaf . 'Image'
                || $type == $nsAair . 'Activity'
            ) {
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
    public function feedToActivity ($feedData)
    {
        // load feedxml and display activities
        $feed = DSSN_Activity_Feed_Factory::newFromXml($feedData);
        $activityController = $this->_app->getController('Xodx_ActivityController');

        $nsXodx = 'http://xodx.org/ns#';
        $nsXsd = 'http://www.w3.org/2001/XMLSchema#';

        foreach ($feed->getActivities() as $key => $activity) {
            $date = $activity->getPublished;
            //$title = $activity->getTitle();
            $title = 'Imported with DSSN-LIB';
            $actorUri = $activity->getActor();
            $verbUri = $activity->getVerb();
            $objectUri = $activity->getObject();
            $contextUri = $activity->getTarget();
            $activity[] = new Activity(null, $actorUri, $verbUri, $objectUri, $date);
            $activityController->addActivities($activity);
        }
    }

    public  function testFeedAction ()
    {
        $feed = '<?xml version="1.0" encoding="utf-8"?>
<feed xmlns:activity="http://activitystrea.ms/schema/1.0/" xmlns="http://www.w3.org/2005/Atom">
    <title>Activity Feed for Norman Radtke</title>
    <id>http://splatatter.comiles.eu/xodx/?c=person&amp;id=splatte</id>
    <link href="http://pubsubhubbub.appspot.com" rel="hub"/>
    <link href="http://splatatter.comiles.eu/xodx/?c=feed&amp;a=getFeed&amp;uri=http%3A%2F%2Fsplatatter.comiles.eu%2Fxodx%2F%3Fc%3Dperson%26id%3Dsplatte" type="application/atom+xml" rel="self"/>
    <updated>2012-10-17T13:46:58+02:00</updated>

<entry>
      <title>"Norman Radtke" did "http://xmlns.notu.be/aair#Post" a "http://rdfs.org/sioc/ns#Comment"</title>
      <id>http://splatatter.comiles.eu/xodx/?c=resource&amp;id=77f46b1bb479c098c69997d914ab437f</id>
      <link href="http://splatatter.comiles.eu/xodx/?c=resource&amp;id=77f46b1bb479c098c69997d914ab437f"/>
      <published>2012-10-17T13:46:58+02:00</published>
      <updated>2012-10-17T13:46:58+02:00</updated>
      <author>
        <name>Norman Radtke</name>
        <uri>http://splatatter.comiles.eu/xodx/?c=person&amp;id=splatte</uri>
      </author>
      <activity:verb>http://xmlns.notu.be/aair#Post</activity:verb>
      <activity:object>
        <id>http://splatatter.comiles.eu/xodx/?c=resource&amp;id=4fb8e32ade09fe78ebaa97dd09cf94d5</id>

        <content>Ein Test!</content>

        <published>2012-10-17T13:46:58+02:00</published>

        <activity:object-type>http://rdfs.org/sioc/ns#Comment</activity:object-type>
              </activity:object>

          </entry>
</feed>
        ';
        $this->feedToActivity($feed);
    }
}
