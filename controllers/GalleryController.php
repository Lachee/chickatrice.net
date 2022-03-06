<?php namespace app\controllers;

use app\components\mixer\Mixer;
use app\models\Gallery;
use app\models\ScrapeData;
use app\models\Tag;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\models\BaseObject;
use app\models\User;
use Exception;
use Chickatrice;
use kiss\exception\NotYetImplementedException;
use kiss\helpers\Strings;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

class GalleryController extends BaseController {

    /** Home Page, displays latest information */
    function actionIndex() {
        /** @var User $user */
        $user = Kiss::$app->user;
        $limit = 10;

        $galleries = [
            'latest'            => Gallery::findByLatest(),
            'top_rated'         => Gallery::findByRating(),
            'submitted'         => [], 
            'favourites'        => [], 
            'recommendation'    => [],
        ];
        
        if ($user != null) {
            $galleries['submitted'] = $user->getGalleries();
            $galleries['favourites'] = $user->getFavouriteGalleries();
            $galleries['recommendation'] = $user->searchRecommdendedGalleries(0, $limit);

            $galleries['latest']->andWhere([ 'founder_id', '!=', $user ]);

            //Query and blacklist the galleries
            foreach($galleries as $k => $gallery) {
                if (!is_array($gallery))
                    $galleries[$k] = $user->applyGalleryBlacklist($gallery)->orderByDesc('id')->limit($limit)->all();
            }
        } else {
            
            //Query all the galleries
            foreach($galleries as $k => $gallery) {
                if (!is_array($gallery))
                    $galleries[$k] = $gallery->orderByDesc('id')->limit($limit)->all();
            }
        }

        return $this->render('index', $galleries);
    }

    function actionBrowse() {
        return $this->render('browse', []);
    }

    /** New fancy search that browses the page */
    function actionQuery() {
        $query = HTTP::get('q', HTTP::get('gall-q', false));
        if ($query === false || empty($query))
            return Response::redirect(['/gallery/browse']);

        //Return to ourselves
        if (Strings::startsWith($query, '@me')) {
            if (!Kiss::$app->loggedIn()) Response::redirect(['/login']);
            $requests = explode(' ', $query);
            return Response::redirect(['/profile/' . join('/', $requests)]);
        }

        //If its an interger, probably a gallery
        if (is_numeric($query)) {
            $gallery = Gallery::findByKey($query)->fields(['id'])->one();
            if ($gallery) return Response::redirect(['/gallery/:gallery/', 'gallery' => $gallery]);
        }

        //If the query starts with HTTP then we want to find based of url
        if (($link = Strings::likeURL($query)) !== false) {        
            //If it exists, lets go to it
            $gallery = Gallery::findByUrl($link)->fields(['id'])->one();
            if ($gallery == null) {

                //See if we are trying to scrape ourseles
                $cleanURL = preg_replace("(^https?://)", "", $link );
                $cleanBase = preg_replace("(^https?://)", "", Kiss::$app->baseURL());
                if (Strings::startsWith($cleanURL, $cleanBase)) {
                    $fio = strpos($cleanURL, '/');
                    $cleanURL = substr($cleanURL, $fio);
                    if (preg_match('/\/gallery\/([0-9]*)\/?/', $cleanURL, $matches)) {
                        return Response::redirect(['/gallery/:gallery/', 'gallery' => $matches[1]]);                        
                    }

                    //Just give up
                    throw new Exception('Gallery does not exist');
                }

                // Verify we are logged in before we try to publish
                if (!Kiss::$app->loggedIn()) 
                    throw new HttpException(HTTP::UNAUTHORIZED, 'You need to be logged in to query');
                
                //It doesn't exist, so lets make a new post
                $scraped_data = Chickatrice::$app->scraper->scrape($link);
                if (!($gallery = $scraped_data->publish(Kiss::$app->user))) {

                    //We failed to publish the gallery, oh dear :c
                    Kiss::$app->session->addNotification('Failed to create the gallery. ' . $scraped_data->errorSummary(), 'danger');
                    return Response::redirect(['/gallery/']);
                }
            }

            //Redirect to the gallery
            return Response::redirect(['/gallery/:gallery/', 'gallery' => $gallery]);
        }

        //Check to see if it is a profile?
        if (($profile = User::findByProfileName($query)->andWhere(['anon_bot', '0'])->one()) != null && $profile->getSnowflake() != 0)
            return Response::redirect(['/profile/:profile/', 'profile' => $profile->profileName]);

        return Response::redirect([ 'browse', 'q' => $query ]);
    }

    /** Deprecated search that displays it as cards */
    function actionSearch() {

        $page           = HTTP::get('page', 0);
        $pageLimit      = HTTP::get('limit', 10);
        $query          = HTTP::get('q', HTTP::get('gall-q', HTTP::get('tag', false)));
     
        if ($page <= 0) $page = 1;

        $results        = Gallery::search($query, Chickatrice::$app->getUser())->limit($pageLimit, ($page-1) * $pageLimit)->all();
        return $this->render('list', [
            'results'   => $results
        ]);
    }
}