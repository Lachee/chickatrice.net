<?php namespace app\models;

use Exception;
use kiss\models\Identity;
use GALL;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\db\Query;
use kiss\exception\ArgumentException;
use kiss\exception\InvalidOperationException;
use kiss\exception\NotYetImplementedException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\SQLException;
use kiss\helpers\Arrays;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\K;
use kiss\Kiss;
use kiss\schema\BooleanProperty;
use kiss\schema\IntegerProperty;
use kiss\schema\RefProperty;
use kiss\schema\StringProperty;

/**
 * @property int $sparkles the number of sparkles a user has
 * @package app\models
 */
class User extends Identity {
    
    /** @var \app\components\discord\User stored discord user. */
    private $_discordUser;
    protected $profile_name = null;
    protected $profile_image;
    protected $snowflake;
    protected $score;
    protected $last_seen;
    protected $anonymise = true;

    public static function getSchemaProperties($options = [])
    {
        return [
            'uuid'          => new StringProperty('ID of the user'),
            'snowflake'     => new StringProperty('Discord Snowflake id'),
            'username'      => new StringProperty('Name of the user'),
            'displayName'   => new StringProperty('Name of the user'),
            'profileName'   => new StringProperty('Name of the user\'s profile'),
            'profileImage'  => new RefProperty(Image::class, 'Profile image'),
            'sparkles'      => new StringProperty('Number of sparkles the user has', [ 'readOnly' => true ]),
            'anonymise'     => new BooleanProperty('Hides the user details from guests'),
            'last_seen'     => new StringProperty('Last time this user was active')
        ];
    }

    /** @return string Current discord snowflake of the logged in user. */
    public function getSnowflake() { 
        if ($this->getAnonymised()) return '0';
        return $this->snowflake; 
    }

    /** @return bool returns if the profile is currently anonymised */
    public function getAnonymised() {
        return !Kiss::$app->loggedIn() && $this->anonymise;
    }

    /** Finds by snowflake */
    public static function findBySnowflake($snowflake) {
        return self::find()->where(['snowflake', $snowflake]);
    }

    /** Gets the current Discord user
     * @return \app\components\discord\User the discord user
     */
    public function getDiscordUser() {
        if ($this->_discordUser != null) return $this->_discordUser;
        $storage = GALL::$app->discord->getStorage($this->uuid);
        $this->_discordUser = GALL::$app->discord->identify($storage);
        return $this->_discordUser;
    }

    /** Gets the discord guilds */
    public function getDiscordGuilds() {
        $storage = GALL::$app->discord->getStorage($this->uuid);
        return GALL::$app->discord->getGuilds($storage);
    }

    /** Runs a quick validation on the discord token
     * @return bool true if the token is valid
     */
    public function validateDiscordToken() {
        if ($this->_discordUser != null) return true;
        
        $storage = GALL::$app->discord->getStorage($this->uuid);
        return GALL::$app->discord->validateAccessToken($storage);
    }

#region Profile
    /** Gets the URL of the users avatar
     * @return string the URL
     */
    public function getAvatarUrl($size = 64) {
        $url = "https://d.lu.je/avatar/{$this->getSnowflake()}?size=$size";
        return $url;
    }
    /** @return ActiveQuery|Image gets the profile image */
    public function getProfileImage() {
        if (empty($this->profile_image)) {
            $bestGallery = $this->getBestGalleries()->limit(1)->one();
            if ($bestGallery) return $bestGallery->cover;
            return null;
        }
        return Image::findByKey($this->profile_image)->limit(1);
    }
    /** Sets the profile image
     * @param Image $image
     * @return $this
     */
    protected function setProfileImage($image) {
        if ($image instanceof Image) $image = $image->getKey();
        $this->profile_image = $image;
        $this->markDirty('profile_image');
        return $this;
    }

    /** @return string the name of the profile page. Some users may have a custom one. */
    public function getProfileName() {
        if ($this->getAnonymised()) return $this->uuid->toString();
        return !empty($this->profile_name) ? $this->profile_name : $this->snowflake;
    }
    /** @return string the name to display to others. */
    public function getDisplayName() {
        if ($this->getAnonymised()) return 'anonymous';
        return !empty($this->profile_name) ? $this->profile_name :  $this->username;
    }
    /** @return string the username */
    public function getUsername() {
        if ($this->getAnonymised()) return 'anonymous';
        return $this->username;
    }
#endregion

#region Galleries
    /** @return ActiveQuery|Gallery[] the best galleries the user has submitted */
    public function getBestGalleries() {
        return $this->getGalleries()->orderByDesc('views');
    }

    /** @return ActiveQuery|Gallery[] the galleries the user submitted themselves */
    public function getGalleries() {
        return Gallery::findByFounder($this);
    }

    /** @return int the number of galleries the user has */
    public function getGalleryCount() {
        return $this->getGalleries()->select(null, [ 'COUNT(*)' ])->one(true)['COUNT(*)'];
    }

    /** Searches galleries for tags that the user follows 
     * @return ActiveQuery|Gallery[]  */
    public function searchRecommdendedGalleries($page, $limit) {
        $tags = $this->getFavouriteTags()->limit(5)->all();
        if (count($tags) == 0) $tags = $this->getFavouriteTagsSubmitted()->limit(5)->all();
        if (count($tags) == 0) return $this->getBestGalleries()->limit(5)->all();

        return Gallery::search([], $this, $tags)                    // Search for the tags, excluding our blacklist
                        ->andWhere(['founder_id', '<>', $this])     // Exclude our own posts
                        ->orderByDesc('id');                        // Sort by newest
    }
#endregion

#region Favourites
    /** @return ActiveQuery|Tag[] gets the users favourite tags */
    public function getFavouriteTags() {
        return Tag::find()
                    ->fields(['*, COUNT(*) as C'])
                    ->leftJoin('$tags', [ 'id' => 'tag_id' ])
                    ->rightJoin(Favourite::class, ['$tags.gallery_id' => 'gallery_id'])
                    ->groupBy('$tags.tag_id')
                    ->where(['user_id', $this])
                    ->orderByDesc('C')->limit(5)->ttl(5);
    }

    /** @return ActiveQuery|Tag[] gets the tags the user most commonly submits */
    public function getFavouriteTagsSubmitted() {
        return Tag::find()
                    ->fields(['*, COUNT(*) as C'])
                    ->leftJoin('$tags', [ 'id' => 'tag_id' ])
                    ->groupBy('$tags.tag_id')
                    ->where(['$tags.founder_id', $this])
                    ->orderByDesc('C')->limit(5)->ttl(5);
    }

    /** Adds a gallery to the user's favourites
     * @param Gallery $gallery the gallery to add
     * @return Favourite|false the resulting favourite. Will return false if unable to add
     */
    public function addFavourite($gallery) {
        $galleryid = $gallery instanceof Gallery ? $gallery->getKey() : intval($gallery);
        $favourite = new Favourite([ 'gallery_id' => $galleryid, 'user_id' => $this->getKey() ]);
        if (!$favourite->save()) return false;

        //Award the points if not a self fav
        if ($gallery->founder_id != $this->id) {
            $gallery->founder->giveSparkles('SCORE_FAVOURITED', $gallery, $this->getKey());
            $this->giveSparkles('SCORE_FAVOURITE', $gallery);

            if ($gallery->founder->hasBeenViewedRecently()) {
                $gallery->founder->giveSparkles('SCORE_FAVOURITE_REFERAL', $gallery, $this->getKey());
            }
        }

        return $favourite;
    }

    /** Removes the gallery from the user's favourites
     * @param Gallery $gallery the gallery to add
     * @return bool True if it was deleted
     */
    public function removeFavourite($gallery) {
        $favourite = Favourite::findByProfile($this)->andWhere(['gallery_id', $gallery])->one();
        if ($favourite == null) return false;
        
        //Unaward the points
        if ($gallery->founder_id != $this->id) {
            $gallery->founder->takeSparkles('SCORE_FAVOURITED', $gallery, $this->getKey());
            $this->takeSparkles('SCORE_FAVOURITE', $gallery);
            $gallery->founder->takeSparkles('SCORE_FAVOURITE_REFERAL', $gallery, $this->getKey());
        }

        return $favourite->delete();
    }

    /** @return bool returns if the user has favourited a particular gallery */
    public function hasFavouritedGallery($gallery) {
        $results = Favourite::findByProfile($this)->andWhere(['gallery_id', $gallery])->ttl(false)->one(true);
        return $results != null;
    }
    
    /** @return ActiveQuery|Favourite[] gets the favourites */
    public function getFavouriteCount() {
        return Favourite::findByProfile($this)->select(null, [ 'COUNT(*)' ])->one(true)['COUNT(*)'];
    }

    
    /** @return ActiveQuery|Gallery[] get the favourite galleries */
    public function getFavouriteGalleries() {
        return Gallery::find()->leftJoin(Favourite::class, [ '$gallery.id' => 'gallery_id' ])->where(['user_id', $this ]);
    }

#endregion

#region Guilds
    /** Gets the guilds the user has posted in */
    public function getGuilds() {        
        return Guild::find()->leftJoin('$users_guilds', ['id' => 'guild_id'])->where(['user_id', $this->id]);
    }

    /** Checks if the user is in the guild
     * @param Guild|ActiveRecord|string|int $guild the guild
     * @return bool true if they are in the guild
     */
    public function inGuild($guild) {
        $guild_id = $guild instanceof ActiveRecord ? $guild->getKey() : $guild;
        return $this->getGuilds()->andWhere(['guild_id', $guild_id])->one(true) != null;
    }

    /** Adds the user to the guild if able
     * @param Guild|ActiveRecord|string|int $guild the guild
     * @return bool true if they are in the guild
     */
    public function addGuild($guild) {
        if (!$this->inGuild($guild)) {
            $guild_id = $guild instanceof ActiveRecord ? $guild->getKey() : $guild;
            GALL::$app->db()->createQuery()->insert(['user_id' => $this->id, 'guild_id' => $guild_id ], '$users_guilds')->execute();
        }
    }
#endregion

#region History
    /** Records the current user was browsing this user's profile 
     * @return $this
    */
    public function recordViewage() {
        Kiss::$app->db()->createQuery()
                                ->insert(['user_id' => Kiss::$app->user->getKey(), 'profile_id' => $this->getKey() ], '$profile_history')
                                ->execute();
        return $this;
    }

    /** Checks if the current user has recently viewed this profile
     * @return bool true if they have
     */
    public function hasBeenViewedRecently() {
        $results = Kiss::$app->db()->createQuery()
                                ->select('$profile_history', [ 'date_viewed' ])
                                ->where([ 'user_id', Kiss::$app->user->getKey() ])
                                ->andWhere([ 'profile_id', $this->getKey() ])
                                ->andWhere([ 'date_viewed >= now() - INTERVAL 1 MINUTE' ])
                                ->limit(1)
                                ->execute();

        return $results !== false && count($results) > 0;
    }

    /** Updates that hte user has been seen */
    public function seen() {
        $stm = Kiss::$app->db()->prepare('UPDATE $users SET `last_seen` = now() WHERE `id` = :id');
        $stm->bindParam(':id', $this->id);
        $stm->execute();
    }
#endregion

#region Blacklist
    /** Adds a tag to the blacklist
     * @param Tag|int $tag the tag to add
     * @return $this
     */
    public function addBlacklist($tag) {
        Kiss::$app->db()->createQuery()
                                ->insert(['user_id' => $this->id, 'tag_id' => $tag instanceof Tag ? $tag->getKey() : $tag ], '$blacklist')
                                ->execute();
        return $this;
    }

    /** @return Query returns the active query for the basic blacklists */
    public function getBlacklist() {
        return Kiss::$app->db()->createQuery()
                                    ->select('$blacklist')
                                    ->where([ 'user_id', $this->id ]);
    }

    /** Applies the user's blacklist as a WHERE condition to the query.
     * @param ActiveQuery $galleryQuery Must be a query for the Gallery. Will throw otherwise.
     * @return ActiveQuery The modified query.
     */
    public function applyGalleryBlacklist($galleryQuery) {
        if (!($galleryQuery instanceof ActiveQuery) || $galleryQuery->class() != Gallery::class)
            throw new ArgumentException('Unable to apply blacklist gallery as the query is not a Gallery query');

        return $galleryQuery->andWhere(['id', 'NOT', Gallery::find()
                                ->fields(['$gallery.id'])
                                ->leftJoin('$tags', ['id' => 'gallery_id'])
                                ->where(['tag_id', $this->getBlacklist()->fields([ 'tag_id' ]) ])
                            ]);
    }
#endregion Blacklist

#region Auto-Tag
    /** @return Query returns the query for the auto tags */
    public function getAutoTags() {
        return Kiss::$app->db()->createQuery()
                                    ->select('$auto_tags')
                                    ->where(['user_id', $this->id]);
    }

    /** Creates an autotag
     * @param Emote|int $emote the emote
     * @param Tag|int $tag the tag
     * @return $this
     */
    public function addAutoTag($emote, $tag) {
        Kiss::$app->db()->createQuery()
                            ->insert([
                                'user_id'   => $this->id, 
                                'tag_id'    => $tag instanceof Tag ? $tag->getKey() : $tag,
                                'emote_id'  => $emote instanceof Emote ? $emote->getKey() : $emote,
                            ], '$auto_tags')
                            ->execute();
        return $this;
    }
#endregion

#region Reactions
    /** Reacts to a gallery
     * @param Gallery $gallery
     * @param Emote $emote
     * @return $this
     */
    public function addReaction($gallery, $emote) {
        try {
            //Add the reactions
            $success = Kiss::$app->db()->createQuery()
                            ->insert([ 
                                'user_id'       => $this->getKey(),
                                'gallery_id'    => $gallery->getKey(),
                                'emote_id'      => $emote->getKey()
                            ], '$reaction')->execute();
        } catch(SQLDuplicateException $dupeException) { return $this; }

        //Award the original author
        $gallery->founder->giveSparkles('SCORE_REACTION', $gallery, $this->getKey() . ',' . $emote->getKey());

        //Apply Autotag
        $tagged = [];
        $autoTags = $this->getAutoTags()->andWhere(['emote_id', $emote->getKey()])->execute();
        Kiss::$app->db()->beginTransaction();
        try {
            foreach($autoTags as $at) {
                try {
                    $success = $gallery->addTag($at['tag_id'], $this);
                    if ($success) $tagged[] = $at;
                }catch(SQLDuplicateException $dupeException) { /** no-op for duplicates */}
            }
            Kiss::$app->db()->commit();
        }catch(Exception $e) {
            Kiss::$app->db()->rollBack();
            throw $e;
        }

        return $this;
    }

    /** Unreacts to a gallery
     * @param Gallery $gallery
     * @param Emote $emote
     */
    public function removeReaction($gallery, $emote) {
        $rowCount = Kiss::$app->db()->createQuery()
                                    ->delete('$reaction')
                                    ->andWhere(['user_id', $this->getKey()])
                                    ->andWhere(['gallery_id', $gallery->getKey()])
                                    ->andWhere(['emote_id', $emote->getKey()])
                                    ->execute();

        //Remove the sparkles given for adding a reaction
        $gallery->founder->takeSparkles('SCORE_REACTION', $gallery, $this->getKey() . ',' . $emote->getKey());
        return $this;
    }
#endregion

    /** @return bool is the profile the signed in user */
    public function isMe() {
        if (Kiss::$app->user == null) return false;
        return $this->id == Kiss::$app->user->id;
    }

    /** @inheritdoc */
    public function login() {

        //Undo the anonymise for new accounts
        if (empty($this->last_seen)) { 
            $this->anonymise = false;
            $this->markDirty('anonymise');
            $this->save(false, [ 'anonymise' ]);
        }

        //Login
        return parent::login();
    }

    /** Gives the user a specific amount of sparkles 
     * @param Sparkle|string|int $sparkles the sparkles to give. If a Sparkle object, then just that record will be used. If a string, then it will look up that constant name.
     * @param Gallery|null $gallery the gallery the sparkles originate from
     * @param string|null $resource the extra key data to distinguish records.
     * @param string $type the score type. This will be ignored if the $sparkles is a string
     * @return Sparkle
    */
    public function giveSparkles($sparkles, $gallery = null, $resource = null, $type = 'SCORE_UNKOWN') { 
        if ($sparkles instanceof Sparkle) {
            $sparkles->markNewRecord();
            $sparkles->id       = null;
            $sparkles->user_id  = $this->id;
            $sparkles->save();
            return $sparkles;
        }

        if (is_string($sparkles) && Strings::startsWith($sparkles, 'SCORE_')) {
            $class = new \ReflectionClass(Sparkle::class);
            $value = $class->getConstant(strtoupper($sparkles));
            if ($value !== false) {
                $type       = substr(strtoupper($sparkles), 6);
                $sparkles   = $value;
            }
        }

        $spark = new Sparkle([
            'user_id' => $this->id,
            'type'      => $type,
            'score'     => ceil($sparkles),
            'gallery_id'    => $gallery instanceof ActiveRecord ? $gallery->getKey() : $gallery,
            'resource'  => $resource instanceof ActiveRecord ? $resource->getKey() : $resource,
        ]);
        $spark->save();
        return $spark;
    }

    
    /** Creates a new record that is the negative of the sparkles previously given
     * @param string $type the score type. This will be ignored if the $sparkles is a string
     * @param Gallery|null $gallery the gallery the sparkles originate from
     * @param string|null $resource the extra key data to distinguish records.
     * @return Sparkle|false returns the new sparkle negative point item, otherwise null.
    */
    public function takeSparkles($type, $gallery = null, $resource = null) { 

        //Remove the SCORE_ prefix
        if (Strings::startsWith($type, 'SCORE_')) {
            $type = substr(strtoupper($type), 6);
        }

        //Get existing sparkle
        $query = Sparkle::find()->where(['user_id', $this->id ])->andWhere(['type', $type])->orderByDesc('id');
        if ($gallery != null) $query->andWhere(['gallery_id', $gallery]);
        if ($resource != null) $query->andWhere(['resource', $resource]);
        $sparkle = $query->one();;

        if ($sparkle == null)
            return false;

        //Inverse
        $sparkle->score *= -1;
        $sparkle->type = 'UN' . $sparkle->type;
        return $this->giveSparkles($sparkle);
    }

    /** @return int number of sparkles the user has */
    public function getSparkles() { return $this->score; }

    /** @return ActiveQuery|Sparkle[] history of sparkles. */
    public function getSparkleHistory() {
        return Sparkle::find()->where(['user_id', $this ])->orderByDesc('id');
    }

    /** Recomputes the number of sparkles the user has. 
     * @return int number of sparkles */
    public function recalculateSparkles() {
        $query = Kiss::$app->db()->createQuery()
                        ->select('$sparkles', [ 'SUM(score) as SCORE' ])
                        ->where(['user_id', $this->getKey() ])
                        ->execute();
        
        $sparkles = $query[0]['SCORE'];
        $this->score = $sparkles ?? 0;
        $this->save(false, ['score']);
        return $sparkles;
    }

    /** @return ActiveQuery|$this finds the profile from the given name */
    public static function findByProfileName($profile) {
        if ($profile == '@me') {
            return self::findByKey(Kiss::$app->user->getKey());
        }
        return self::findBySnowflake($profile)->orWhere(['profile_name', $profile]);
    }

    /** @return ActiveQuery|$this finds an anonymous account to post under */
    public static function findAnnon() {
        return self::find()->where(['anon_bot', true]);
    }
}