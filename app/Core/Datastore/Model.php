<?php namespace App\Core\Datastore;

use ReflectionClass;
use Doctrine\Common\Inflector\Inflector;

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
abstract class Model// implements ArrayAccess, JsonSerializable
{  
  
  /**
   * Reference to the Datastore class
   *
   * @var App\Core\Datastore\DS
   */
  protected $ds;
  
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
   * Constructor
   *
   * @param array $properties optional. initial properties of this object
   * @param DS $ds optional. reference to the datastore class
   * @param string $kind optional kind of entity. this is left out if instantiating
   *                     from sub classes.
   */
  function __construct(array $properties = [], Datastore $ds = null)
  {
    $this->ds = $ds;
    $this->hydrate($properties);
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
      return $this->dirty[$name];
    }
    elseif( isset($this->properties[$name]) )
    {
      return $this->properties[$name];
    }
    else
    {
      throw new \Exception(sprintf('%s has no property named %s.', get_class($this), $name));
    }
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
      if ( $ds === null && $this->ds !== null )
      {
        $ds = $this->ds;
      }
      elseif ( $ds === null && $this->ds === null )
      {
        throw new \Exception('Cannot save model without a datastore.');
      }
      $ds->put($this);
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
  
}