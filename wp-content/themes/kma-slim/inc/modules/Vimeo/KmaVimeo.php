<?php
namespace Includes\Modules\Vimeo;

use Vimeo\Vimeo;

class KmaVimeo
{
    public function videos($page, $perPage = 4)
    {
        $library = new Vimeo(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET);

        $results = $library->request('/users/18180229/videos', ['per_page' => $perPage, 'page' => $page], 'GET');

        return $results['body'];
    }
}
