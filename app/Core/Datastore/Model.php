<?php namespace App\Core\Datastore;

use ReflectionClass;
use App\Core\Observable;
use Carbon\Carbon;
use Doctrine\Common\Inflector\Inflector;
use Respect\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationExceptionInterface;

/**
 * Model Class
 *
 * A basic ActiveRecord implementation for the Silver app. Models contain logic
 * that pertains to the app's data, e.g. validation, formatting
 * and conversion.
 *
 * @package Silver
 * @author Brux
 * @since 0.1.0
 */
abstract class Model implements \JsonSerializable {
  use Observable;

  /**
   * Reference to the Datastore class
   *
   * @var App\Core\Datastore\DS
   */
  protected $ds;

    /**
     * Reference to the Schema manager.
     * 
     * @var Schema
     */
    protected $schema;

  /**
   * The object's persisted properties.
   *
   * @var array
   */
  protected $properties = [];

  /**
   * Contains properties that aren't persisted yet.
   *
   * @var array
   */
  protected $dirty = [];

  /**
   * The kind of this entity. This is only filled when
   * invoking Model::getKind().
   *
   * @var string
   */
  protected $kind = null;

    /**
     * This entity's validation errors.
     * 
     * @var array
     */
    protected $validationErrors = [];

    /**
     * The model's custom validation rules.
     * 
     * This varible should be an associative array with
     * fields as the array's keys and a string or array of rules as their values.
     * Example:
     * [
     *   'name'     => 'alnum|notEmpty',
     *   'age'      => 'positive|min:1,true',
     *   'website'  => ['notEmpty', 'Url', ['Custom Rule', 'arg1', 'arg2']]
     * ]
     * 
     * @var array
     */
    protected static $validationRules = [];

    /**
     * Contains the processed validation rules so we don't need
     * to reprocess the rules everytime we need them.
     * 
     * @var array
     */
    protected static $_validationRules = null;

    /**
     * Constructor
     *
     * @param array $properties optional. initial properties of this object
     * @param DS $ds optional. reference to the datastore class
     * @param Schema $schema optional. schema for this entity kind
     */
    function __construct(array $properties = [], Datastore $ds = null, Schema $schema = null)
    {
        $this->ds = $ds;
        $this->schema = $schema;
        $this->hydrate($properties);
        $this->setup();
    }

  /**
   * Perform any setup you need here.
   * 
   * @return void
   */
  protected function setup()
  {
    
  }

  /**
   * Fills the object's properties without marking them as dirty.
   *
   * @param array $props array of properties
   * @return void
   */
  public function hydrate(array $props = [])
  {
    foreach ( $props as $name => $value )
    {
      $this->properties[$name] = $value;
    }
  }

  /**
   * Retrieve a property value.
   *
   * @param string $name property name
   * @return mixed
   */
  public function get($name)
  {
    if ( isset($this->dirty[$name]) )
    {
      $value = $this->dirty[$name];
    }
    elseif( isset($this->properties[$name]) )
    {
      $value = $this->properties[$name];
    }
    else
    {
      throw new \Exception(sprintf('%s has no property named %s.', get_class($this), $name));
    }
    return $this->trigger("get_$name", $value);
  }

  /**
   * Sets a property value.
   *
   * @param string $name property name
   * @param mixed $value new property value
   * @return this
   */
  public function set($name, $value)
  {
    $value = $this->trigger("set_$name", $value);
    $this->dirty[$name] = $value;
    return $this;
  }

  /**
   * Returns TRUE if a property is present.
   *
   * @param string $name property name
   * @return bool
   */
  public function has($name)
  {
    if ( isset($this->properties[$name]) || isset($this->dirty[$name]) )
    {
      return true;
    }
    else
    {
      return false;
    }
  }

  /**
   * Returns TRUE if this is a new record.
   *
   * @return bool
   */
  public function isNew()
  {
    return !isset($this->properties['id']);
  }

  /**
   * Returns TRUE if this model has unsaved properties or if $name is
   * provided, returns TRUE if this model has an unsaved property named $name.
   *
   * @param string $name optional name of property to check
   * @return bool
   */
  public function isDirty($name = null)
  {
    if ( $name === null )
    {
      return !empty($this->dirty);
    }
    else
    {
      return isset($this->dirty[$name]);
    }
  }

    /**
     * Performs a validation check.
     * 
     * @return boolean
     */
    public function check()
    {
        $this->validationErrors = [];
        $result = true;
        $all_rules = static::buildValidationRules();
        $fields = array_keys($this->getProperties());
        foreach ( $all_rules as $field => $rules )
        {

            if ( isset($rules['required']) )
            {
                $required_field = true;
                unset($rules['required']);
            }
            else
            {
                $required_field = false;
            }

            // Add our rules to the validator chain
            $v = new Validator();
            foreach ( $rules as $rule => $rule_args )
            {
                call_user_func_array(array($v, $rule), $rule_args);
            }

            // Assert the value
            $errors = [];
            $passed = true;
            if ( $this->has($field) )
            {
                try
                {
                    $passed = $v->assert($this->get($field));
                }
                catch ( NestedValidationExceptionInterface $ex )
                {
                    $passed = false;
                    $this->validationErrors[$field] = array_filter($ex->findMessages(array_keys($rules)));
                }
            }
            elseif ( ! $this->has($field) && $required_field )
            {
                $errors[] = sprintf('"%s" is required.', $field);
                $passed = false;
            }

            // If one rule fails, all fails.
            if ( $passed === false )
            {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Returns TRUE if the entity's properties follow the model's custom
     * and default validation rules.
     * 
     * @return boolean
     */
    public function isValid()
    {
        return $this->check();
    }

    /**
     * Returns this entity's validation errors. Call this method after
     * invoking isValid().
     * 
     * @return array
     */
    public function validationErrors()
    {
        return $this->validationErrors;
    }

  /**
   * Returns an array of properties that hasn't been saved yet.
   *
   * @return array
   */
  public function getDirtyProperties()
  {
    return $this->dirty;
  }

  /**
   * Returns the properties as an array.
   *
   * @return array
   */
  public function getProperties()
  {
    $props = array_merge($this->properties, $this->dirty);
    return $props;
  }

    /**
     * Saves the model to the datastore.
     *
     * @param App\Core\Datastore\DS $ds optional. save the model to this Datastore
     * @return this
     */
    public function save(Datastore $ds = null)
    {
        if ( $this->isDirty() )
        {
            $this->trigger('before_validate', $this);
            if ( $this->isValid() )
            {
                $this->trigger('after_validate', $this);
                if ( $ds === null && $this->ds !== null )
                {
                    $ds = $this->ds;
                }
                elseif ( $ds === null && $this->ds === null )
                {
                    throw new \Exception('Cannot save model without a datastore.');
                }
                $this->trigger('before_save', $this);
                $ds->put($this);
                $this->trigger('after_save', $this);
            }
        }
        return $this;
    }

  /**
   * Clears any unsaved properties, making the entity "clean" again.
   *
   * @return void
   */
  public function refresh()
  {
    $this->dirty = [];
  }

  /**
   * Returns the kind of this entity.
   *
   * @return string
   */
  public function getKind()
  {
    if ( $this->kind === null )
    {
      $reflect = new ReflectionClass($this);
      $this->kind = Inflector::tableize($reflect->getShortName());
    }
    return $this->kind;
  }

  /**
   * Returns the entity where this entity belongs to.
   *
   * @param  string $kind kind of entity this entity belongs to
   * @param  array $opts optional array of options. can contain:
   *                     - foreign_key: the name of the field pointing to the
   *                                    entity we belong to.
   *                     - datastore: the datastore to use
   * @return App\Core\Datastore\Model
   */
  public function belongsTo($kind, array $opts = [])
  {
    $defaults = [
      'foreign_key' => Inflector::tableize($kind) . '_id',
      'datastore' => $this->ds
    ];
    $opts = array_merge($defaults, $opts);
    extract($opts);

    $id = $this->get($foreign_key);
    if ( $id === null )
    {
      return null;
    }
    else
    {
      return $datastore->find($kind, $id);
    }
  }

  /**
   * Returns the entities this entity has/owns.
   *
   * @param  string $kind kind of entities this entity owns
   * @param  array $opts optional array of options. can contain:
   *                     - conditions: more conditions to limit results
   *                     - foreign_key: the name of the field pointing to the
   *                                    entity we belong to.
   *                     - datastore: the datastore to use
   * @return array
   */
  public function hasMany($kind, array $opts = [])
  {
    $defaults = [
      'conditions' => [],
      'foreign_key' => Inflector::tableize($kind) . '_id',
      'datastore' => $this->ds
    ];
    $opts = array_merge($defaults, $opts);
    extract($opts);

    $conditions[$foreign_key] = $this->get('id');

    return $datastore->findCustom($kind, $conditions);
  }

  /**
   * Allows entities to be easily encoded to JSON using json_encode().
   *
   * Take note that this will return dirty and original properties.
   * Make sure to refresh() before encoding if you only want
   * properties that were persisted to the database.
   *
   * @return array
   */
  public function jsonSerialize()
  {
    $props = $this->getProperties();
    foreach ( $props as $field => $value )
    {
      if ( $value instanceof Carbon )
      {
        $props[$field] = $value->toIso8601String();
      }
    }
    return $props;
  }

  /**
   * Catches retrieving of this object's properties.
   *
   * @param string $name property name
   * @return mixed
   */
  public function __get($name)
  {
    return $this->get($name);
  }

  /**
   * Catches setting of this object's properties.
   *
   * @param string $name property name
   * @param mixed $value new property value
   * @return this
   */
  public function __set($name, $value)
  {
    return $this->set($name, $value);
  }

  /**
   * Returns TRUE if a property is present.
   *
   * @param string $name property name
   * @return bool
   */
  public function __isset($name)
  {
    return $this->has($name);
  }

    /**
     * Formats the validation rules set on this model for easier
     * processing later on.
     * 
     * Returns an associative array with keys as entity field names
     * and an array of validation rules as values.
     * 
     * @return array
     */
    protected static function buildValidationRules()
    {
        if ( static::$_validationRules === null )
        {
          $all_rules = static::$validationRules;
          foreach ( $all_rules as $field => $rules )
          {
              // Process string rules (e.g. int|min:5)
              if ( is_string($rules) )
              {
                  $all_rules[$field] = static::processStringRules($rules);
              }
              // For arrays, make sure the keys are the rules.
              elseif ( is_array($rules) )
              {
                  $all_rules[$field] = static::processArrayRules($rules);
              }
          }
          static::$_validationRules = $all_rules;
        }
        return static::$_validationRules;
    }

    protected static function processStringRules($rules)
    {
        $validators = explode('|', $rules);
        $result = [];
        foreach ( $validators as $v )
        {
            // Process arguments, if they're present.
            $v = explode(':', $v);
            if ( count($v) > 1 )
            {
                $v_args = explode(',', $v[1]);
            }
            else
            {
                $v_args = [];
            }
            $result[$v[0]] = $v_args;
        }
        return $result;
    }

    protected static function processArrayRules($rules)
    {
        $new_rules = [];
        foreach ( $rules as $index => $v )
        {
            if ( is_int($index) )
            {
                $new_rules[$v] = [];
            }
            else
            {
                $new_rules[$index] = $v;
            }
        }
        return $new_rules;
    }

}