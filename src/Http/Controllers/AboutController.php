<?php

namespace OpenDominion\Http\Controllers;

class AboutController extends AbstractController
{

    public function getIndex()
    {
        return view('pages.about.index', [
            'url_youtube' => 'https://www.youtube.com/channel/UCGR9htOHUFzIfiPUsZapHhw',
            'url_facebook' => 'https://www.facebook.com/odarenagame/',
            'url_instagram' => 'https://instagram.com/OD_Arena',
            'url_twitter' => 'https://twitter.com/OD_Arena',
        ]);
    }

}
