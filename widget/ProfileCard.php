<?php namespace app\widget;

use app\models\User;
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\widget\Widget;

class ProfileCard extends Widget {
    

    /** @var User $profile */
    public $profile;

    /** @var bool $small should it be a smaller version */
    public $small = false;

    public function init() {
        parent::init();
    }

    public function begin() {
        $profile = $this->profile;
        
        //Prepare the toolbar
        $toolbaritems = [];
        if ($profile->isMe()) {
            $toolbaritems[] = [ 'route' => ['/profile/:profile/settings', 'profile' => $profile->profileName ], 'icon' => 'fa-pencil' ];
            //if ($this->small) {                
            //    $toolbaritems[] = [ 'call' => 'app.api.pin().then(() => setTimeout(() => window.location.reload(), 1000))', 'icon' => 'fa-map-pin' ];
            //}
        } else {
            $toolbaritems[] = [ 'route' => ['/profile/:profile/', 'profile' => $profile->profileName ], 'icon' => 'fa-book-spells' ];
        }
        
        //Prepare the HTML
        $html = '';

        $image = '';
        if ($profile->profileImage) {
            $ext = $profile->profileImage->getOriginExtension();
            if ($ext === '.gif') {
                $profileImageLink = $profile->profileImage->getUrl();  
            } else {
                $profileImageLink = $profile->profileImage->getThumbnail(350);  
            }

            $image = "<div class='card-image'><img src='{$profileImageLink}' alt='{$profileImageLink}'></div>";
        }

        $score = Strings::shortNumber($profile->sparkles);
        $toolbar = self::toolbar($toolbaritems);

        if ($this->small) {
            $profileLink = HTTP::url(['/profile/:profile/', 'profile' => $profile->profileName ]);
            $username = HTML::encode($profile->username);

$html = <<<HTML
    <div class="profile-card is-small">
        <div class="card">
            {$image}
            <div class="card-content">
                <div class="avatar">
                    <img src="{$profile->avatarUrl}" alt="Avatar Picture">
                </div>
                <div class="title"><a href="{$profileLink}" class="has-text-white">{$username}</a></div>
                <div class="subtitle"><span class="icon"><i class="fal fa-sparkles"></i></span> {$score}</div>
            </div>
            {$toolbar}
        </div>
    </div>
HTML;

        } else {
            $favs = Strings::shortNumber($profile->favouriteCount);
            $subs = Strings::shortNumber($profile->galleryCount);
            $tags = $profile->getFavouriteTags()->limit(5)->all();
            if (count($tags) == 0) $tags = $profile->getFavouriteTagsSubmitted()->limit(5)->all();
            $tagsLinks = join(' ', Arrays::map($tags, function($tag) { return '<a href="'.HTTP::url(['/gallery/search', 'tag' => $tag->name ]).'">'. HTML::encode($tag->name).' ( '.$tag->count.' )</a>'; }));
            
            $profileLink = HTTP::url(['/profile/:profile/', 'profile' => $profile->profileName ]);
            $favouriteLink = HTTP::url(['/profile/:profile/favourites', 'profile' => $profile->profileName ]);
            $submissionLink = HTTP::url(['/profile/:profile/submissions', 'profile' => $profile->profileName ]);
            $username = HTML::encode($profile->username);
            $displayName = HTML::encode($profile->displayName);

$html = <<<HTML
    <div class="profile-card">
        <div class="card">
            {$image}
            <div class="card-content">
                <div class="avatar">
                    <img src="{$profile->avatarUrl}" alt="Avatar Picture">
                </div>
                <div class="title">{$username}</div>
                <div class="subtitle"><a href="$profileLink">@{$displayName}</a></div>

                <div class="content">
                    <div class="metric" style="text-align: center;"  data-tooltip="Sparkles"><span class="icon"><i class="fal fa-sparkles"></i></span> {$score}</div>
                    <div class="metric">
                        <a href="$favouriteLink" data-tooltip="Favourites"><span class="icon" ><i class="fal fa-fire"></i></span> {$favs}</a> 
                        <a href="$submissionLink" style="float:right;" data-tooltip="Submissions"><span class="icon"><i class="fal fa-books-medical"></i></span> {$subs}</a></div>
                </div>

                <div class="content">
                    <div class="tag-group">{$tagsLinks}</div>
                </div>
            </div>
            {$toolbar}
        </div>
    </div>
HTML;
        }

        echo $html;
    }

    private static function toolbar($items) {
        if (count($items) == 0) return '';
        $bar = HTML::begin('div', ['class' => 'toolbar']);
        foreach($items as $item) {
            $bar .= HTML::begin('div', ['class' => 'toolbar-item']);
            if (!empty($item['route'])) 
                $bar .= HTML::a($item['route'], HTML::tag('i', '', [ 'class' => 'fal ' . $item['icon'] ]));
            if (!empty($item['call']))
                $bar .= HTML::tag('a', HTML::tag('i', '', [ 'class' => 'fal ' . $item['icon'] ]), [ 'onclick' => $item['call'] ]);

            $bar .= HTML::end('div');
        }
        $bar .= HTML::end('div');
        return $bar;
    }
}