<?php namespace app\controllers;

use app\components\mixer\Mixer;
use app\models\Emote;
use app\models\Gallery;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\models\BaseObject;
use app\models\User;
use Chickatrice;
use kiss\exception\NotYetImplementedException;
use kiss\helpers\Arrays;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

class GalleryViewerController extends BaseController {

    public $gallery_id;
    public static function route() { return "/gallery/:gallery_id"; }

    /** @inheritdoc
     * Discord's scraper is allowed to look at the galleries as it will get redirected to a image anyways
     */
    public function authorize($action) {        
        if (HTTP::isDiscordBot() && $action == 'index') return true;
        return parent::authorize($action);
    }

    function actionIndex() {

        /** @var Gallery $gallery */
        $gallery = $this->gallery;

        //Redirect to the image if we are a bot
        if (HTTP::isDiscordBot()) {
            $url = $this->gallery->cover->proxy;
            return Response::redirect($url);
        }
        
        if (HTTP::get('v', false) !== false) 
            return Response::redirect(['/gallery/:gallery/', 'gallery' => $this->gallery_id ]);

        if (Kiss::$app->loggedIn()) {
            if (!empty($gallery->guild_id) && !Chickatrice::$app->user->inGuild($gallery->guild_id))
                throw new HttpException(HTTP::FORBIDDEN, 'You are not in the correct guild to view this image');

            //Force our tags to update
            $gallery->updateTags();

            //Dont trigger the views if its your own
            if ($gallery->founder_id != Kiss::$app->user->id)
                $gallery->incrementView();
                
        } else {
            if (!Chickatrice::$app->allowVisitors)
                throw new HttpException(HTTP::FORBIDDEN, 'You must be logged in to see a gallery');
        }

        /** @var Image[] $images */
        $images = $gallery->getDisplayImages()->all();
        $reactions = Arrays::map($gallery->getReactions()->execute(), function($r) { 
            return [
                'user'  => User::findByKey($r['user_id'])->one(),
                'emote' => Emote::findByKey($r['emote_id'])->one(),
            ];
        });

        return $this->render('index', [
            'gallery'   => $this->gallery,
            'images'    => $images,
            'reactions' => $reactions
        ]);
    }

    function actionDownload() {
        $title = $this->gallery->title;
        $images = $this->gallery->getImages()->ttl(0)->all();
        $count = count($images);
        switch($count) {
            case 0:
                return Response::redirect($this->gallery->cover->getDownloadUrl($title));
            case 1:
                return Response::redirect($images[0]->getDownloadUrl($title));
            default:
                throw new NotYetImplementedException('Multiple file downloads are not yet supported');
        }
    }

    function actionDelete() {
        $gallery    = $this->gallery;
        $user       = Chickatrice::$app->user;
        if ($user == null || ($user->snowflake != '130973321683533824' && $user != $gallery->founder_id))
             throw new HttpException(HTTP::FORBIDDEN, 'Forbidden from deleting gallery');
        
        //Delete the record
        $gallery->delete();

        Kiss::$app->session->addNotification('Deleted the gallery. The iamges will be deleted in the hour.', 'success');
        return Response::redirect('/gallery/');
    }

    /** Gets the gallery or throws */
    public function getGallery() {        
        /** @var Gallery $gallery */
        $gallery = Gallery::findByKey($this->gallery_id)->one();
        if ($gallery == null) throw new HttpException(HTTP::NOT_FOUND);
        return $gallery;
    }

}