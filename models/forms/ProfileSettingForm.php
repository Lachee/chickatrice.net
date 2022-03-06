<?php namespace app\models\forms;

use app\models\Tag;
use kiss\models\BaseObject;
use app\models\User;
use kiss\exception\InvalidOperationException;
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
use kiss\helpers\Strings;
use kiss\Kiss;
use kiss\models\forms\Form;
use kiss\schema\ArrayProperty;
use kiss\schema\BooleanProperty;
use kiss\schema\ObjectProperty;
use kiss\schema\StringProperty;

class ProfileSettingForm extends Form {
    
    /** @var User $profile */
    protected $profile;

    public $profile_name;
    public $blacklist = [ ];
    public $reaction_emotes = [];
    public $reaction_tags = [];
    public $anonymise = false;

    protected function init()
    {
        parent::init();

        if ($this->profile == null)
            throw new InvalidOperationException('profile cannot be null');

        $this->profile_name = $this->profile->profile_name;
        $this->blacklist = Arrays::map($this->profile->getBlacklist()->fields(['tag_id'])->ttl(0)->execute(), function($t) { return $t['tag_id']; });
        $this->anonymise = $this->profile->anonymise;

        $autoTags = $this->profile->getAutoTags()->ttl(0)->execute();
        foreach($autoTags as $at) {
            $this->reaction_emotes[] = $at['emote_id'];
            $this->reaction_tags[] = $at['tag_id'];
        }
    }

    public static function getSchemaProperties($options = [])
    {
        return [
            'profile_name'      => new StringProperty('Identifier for the profile page', 'cooldude69', [ 'title' => 'Page Name' ]),
            'blacklist'         => new ArrayProperty(new StringProperty('Tag name'), [ 'title' => 'Tag Blacklist', 'description' => 'Tags that will be hidden in recommendations']),
            'reaction_emotes'   => new ArrayProperty(new StringProperty('Reaction id'), [ 'title' => 'Auto Tag', 'description' => 'Automatically tags items when you react with one of these']),
            'reaction_tags'   => new ArrayProperty(new StringProperty('')),
            //'anonymise'         => new BooleanProperty('Hide your account details to guest users', true, [ 'title' => 'Anonymise' ]),
         
            //'api_key'       => new StringProperty('Authorization Token for the API', '', [ 'title' => 'API Key', 'required' => false, 'readOnly' => true ]),
        ];
    }

    protected function beforeLoad($data)
    {
        $this->blacklist = [];
        $this->reaction_emotes = [];
        $this->reaction_tags = [];
    }

    protected function fieldBlacklist($name, $scheme, $options) {
        $values = $this->getProperty($name, []);
        $html = HTML::comment('select2 input');
        $html .= HTML::begin('span', [  'class' => 'select' ]);
        {
            $html .= HTML::begin('select', [ 'name' => $name . '[]', 'multiple' => true, 'class' => 'tag-selector']);
            {
                foreach($values as $key) {
                    $value = Tag::findByKey($key)->fields(['name'])->one();
                    $html .= HTML::tag('option', $value->name, [ 'value' => $key, 'selected' => true ]);
                }
            }
            $html .= HTML::end('select');
        }
        $html .= HTML::end('span');
        return $html;
    }

    protected function fieldReaction_tags($name, $scheme, $options) { return ''; }
    protected function fieldReaction_emotes($name, $scheme, $options) {
$rowTemplate = <<<HTML
<td>
    <div class="field">
        <div class="control" >
            <span class="select"  style="width: 100%">
                <select name="reaction_emotes[]" class="emote-selector"><option value="{emoteKey}" selected>{emoteName}</option></select>
            </span>
        </div>
    </div>
</td>
<td>
    <div class="field">
        <div class="control has-icons-left" >
            <span class="select"  style="width: 100%">
                <select name="reaction_tags[]" class="tag-selector"><option value="{tagKey}">{tagName}</option></select>
            </span>
            <span class="icon is-small is-left"><i class="fal fa-tag"></i></span>
        </div>
    </div>
</td>
HTML;

        $html = HTML::comment('complicated tables woo');
        $html .= HTML::begin('table', ['class' => 'table is-fullwidth']); {
            $html .= HTML::begin('thead tr'); {
                $html .= HTML::tag('th', 'Emote', [ 'width' => '25%']);
                $html .= HTML::tag('th', 'Tag');
            } $html .= HTML::end('thead tr');
            $html .= HTML::begin('tbody'); {
                foreach($this->reaction_emotes as $index => $emoteKey) {
                    if (empty($emoteKey)) continue;              

                    $tagKey = $this->reaction_tags[$index];
                    if (empty($tagKey)) continue;

                    $tag = Tag::findByKey($tagKey)->fields(['name'])->one();
                    if ($tag == null) continue;

                    $tagName = $tag->name;
                    $html .= HTML::begin('tr');      
                    $html .= str_replace([ '{emoteKey}', '{emoteName}', '{tagKey}', '{tagName}'], [ $emoteKey, '', $tagKey, $tagName ], $rowTemplate);
                    $html .= HTML::end('tr');
                }

                $html .= str_replace([ '{emoteKey}', '{emoteName}', '{tagKey}', '{tagName}'], '', $rowTemplate);
            } $html .= HTML::end('tbody');
        } $html .= HTML::end('table');
        return $html;
    }

    /** @inheritdoc */
    public function validate()
    {
        if (!parent::validate()) 
            return false;

        if (!empty($this->profile_name) && $this->profile_name != $this->profile->profileName) {
            $pn = User::findByProfileName($this->profile_name)->one();
            if ($pn != null) {
                $this->addError('Profile name is already in use');
                return false;
            }
        }

        return true;
    }


    public function save($validate = false) {

        //Failed to load
        if ($validate && !$this->validate()) {
            return false;
        }

        //Update the profile information
        if (!empty($this->profile_name)) {
            $this->profile->profile_name = Strings::clean($this->profile_name);
            if (!$this->profile->save()) {
                $this->addError($this->profile->errors());
                return false;
            }
        }

        //Update other metadata
        //$this->profile->anonymise = $this->anonymise;
        $this->profile->save();

        // === BLACKLIST
        //Prepare a list of blacklist items we did have
        $current_blacklist =  Arrays::map($this->profile->getBlacklist()->fields(['tag_id'])->ttl(0)->execute(), function($t) { return $t['tag_id']; });
       
        //Bulk remove all the tags we dont want
        $remove_list = array_diff($current_blacklist, $this->blacklist);
        if (count($remove_list) > 0)
            Kiss::$app->db()->createQuery()
                            ->delete('$blacklist')
                            ->where(['user_id', $this->profile->getKey()])
                            ->andWhere(['tag_id', array_values($remove_list)])
                            ->execute();
        
        //Add all the items we do want (have to do it once at a time)
        $add_list = array_diff($this->blacklist, $current_blacklist);
        foreach($add_list as $id) $this->profile->addBlacklist($id);


        // === AUTOTAGS
        //Clear all the auto tags (its easier this way)
        Kiss::$app->db()->createQuery()
                        ->delete('$auto_tags')
                        ->where(['user_id', $this->profile->getKey()])
                        ->execute();

        //add all the new auto tags
        foreach($this->reaction_emotes as $index => $emoteKey) {
            if (empty($emoteKey)) continue;
            if (empty($this->reaction_tags[$index])) continue;
            $this->profile->addAutoTag($emoteKey, $this->reaction_tags[$index]);
        }

        return true;
    }

}