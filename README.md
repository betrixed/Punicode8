
# Punicode - PHP extension for foreward iteration of unicode string with new PCRE2 interfaces.
This PHP extension, tentatively named "Punicode8", is created using the PHP-CPP toolkit.
The motivation arises from frustrations experienced in engineering the TOML parser projects, toml and toml-zephir, which centred around the interface limitations of preg_match. 
Punicode is compiled with a shared link directly to the latest version of the libpcre2-8 library.
It is currently being developed on a linux system.

## Classes
  The classes, so far, in this first version design, are labelled "Pun\\Pun8", "Pun\\IdRex8", "Pun\\Re8map".
The interface methods are so far, near the minimum needed for the envisioned usage, because of the time investment required.
### Pun\\Pun8 
This was created first. It holds a reference to a PHP string, and maintains an absolute offset property. 
It also has an internal shared IdRex8 map
```php
  // method declarations (implmented in  C++)
  // Start with a utf-8 string to traverse
  public function __construct(string $input);
  // Reset with a new string, or just reset if no argument
  public function setString(string $input);
  // Get next character as one or more byte characters, starting at offset property
  public function nextChar() : string
  // After nextChar() , retrive unicode code point
  public function getCode() : int;
  // Use the offset property, a byte position from 0
  public function getOffset() : int;
  public function setOffset(int $offset);
  public function addOffset(int $offset); 

  /** Just thought of now, not implemented yet, 
   * to maybe return a tuple array [$id, $matches]
   * or [false,false].
   * Because PCRE2 has various more detailed pattern match
   * information results that can be fetched, 
   * a return match results class may be in future scope,
   * such that the caller provides a reuseable class instance to assign
   * the results to.
   */

  public function firstMatch(array $idsToTry) : mixed;

  /** Has a map of Regular expressions. 
   * The internal IdRex8  object has a Id, the expression, and its PCRE2 compiled version.
   * The map is implemented with std::unordered_map
   * Both the IdRex8 internal, and maps  get shared around using std::shared_ptr
   */
  // Give each regular expression algorithmic unique integer key
  public function setIdRex(int $keyId, string $regex);
  public function getIdRex(int $keyId) : IdRex8;
  // Get a normal PHP array of those integer keys (unordered) as a list
  public function getIds() : array;

  // Using the internal map, try a match using reference integer key.
  // Will return array (list) of string matches if found, or PHP false.
  public function matchIdRex(int $key) : mixed;
  // Same as matchIdRex, Using an object that shares a single IdRex
  public function matchIdRex8(IdRex8 $idrex) : mixed;

  // Set the current internal map, from a sharing Map object
  public function setRe8map(Re8map $map);
 
```
### Pun\\IdRex8
This holds a single shareable IdRex8 internal object
```php
  // The Id and the Regular expression are stuck together
  public function __construct(int $id, string $regex);
  public function setIdString(int $id, string $regex);
  // Match results, or false, optional start offset
  public function match(string $target, int $offset = 0) : mixed;
  // Return some properties
  public function getString() : string;
  public function getId() : int;
  public function isCompiled() : bool;

```

### Pun\\Re8map
This object has just a few more map management functions than the Pun8 target string interface.
It is convenient to set the shared Pun8 internal map from one of these, assign a different map  as often as required.
```php
   public setIdRex(int $keyId, string $regex) : int;
   public hasIdRex(int $keyId) : bool;
   // return number of keys unset, 0 or 1
   public unsetIdRex(int $keyId) : int;
   // Create a new IdRex8 object to hold the shared PCRE2.
   public getIdRex(int $keyId) : IdRex8;
   // Add any shared PCRE2, by keyID, not already in this map, return count of new shares
   public shareMap(Re8map $shareMap) : int;
   // Get key Ids as PHP array list of integer
   public getIds() : array;
   // Number of keys in map
   public count() : int;
```
