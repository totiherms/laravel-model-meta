<?php

namespace Vkovic\LaravelModelMeta\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface;
use Vkovic\LaravelModelMeta\Models\Meta;

trait HasMetadata
{
    /**
     * Initialize the trait
     *
     * @return void
     */
    public static function bootHasMetaData()
    {
        // Delete related meta on model deletion
        static::deleted(function (HasMetadataInterface $model) {
            $model->purgeMeta();
        });
    }

    /**
     * Morph many relation
     *
     * @return MorphMany
     */
    public function meta()
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    /**
     * Set meta at given key
     * related to this model (via metable)
     * for package realm.
     * If meta exists, it'll be overwritten.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setMeta($key, $value)
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        $meta = Meta::metable(static::class, $this->id)
            ->where('key', $key)->first();

        if ($meta === null) {
            $meta = new Meta;
            $meta->key = $key;
        }

        $meta->value = $value;

        $this->meta()->save($meta);
    }

    /**
     * Create meta at given key
     * related to this model (via metable)
     * for package realm.
     * If meta exists, exception will be thrown.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws \Exception
     */
    public function createMeta($key, $value)
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        $exists = Meta::metable(static::class, $this->id)
            ->where('key', $key)->exists();

        if ($exists) {
            $message = "Can't create meta (key: $key). ";
            $message .= "Meta already exists";
            throw new \Exception($message);
        }

        $meta = new Meta;

        $meta->key = $key;
        $meta->value = $value;

        $this->meta()->save($meta);
    }

    /**
     * Update meta at given key
     * related to this model (via metable)
     * for package realm.
     * If meta doesn't exists, exception will be thrown.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws \Exception
     */
    public function updateMeta($key, $value)
    {
        try {
            /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
            $meta = Meta::metable(static::class, $this->id)
                ->where('key', $key)->firstOrFail();
        } catch (\Exception $e) {
            $message = "Can't update meta (key: $key). ";
            $message .= "Meta doesn't exist";

            throw new \Exception($message);
        }

        $meta->value = $value;

        $this->meta()->save($meta);
    }

    /**
     * Get meta at given key
     * related to this model (via metable)
     * for package realm
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return array
     */
    public function getMeta($key, $default = null)
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        $meta = Meta::metable(static::class, $this->id)
            ->where('key', $key)->first();

        return $meta === null
            ? $default
            : $meta->value;
    }

    /**
     * Check if meta key record exists by given key
     * related to this model (via metable)
     * for package realm
     *
     * @param string $key
     *
     * @return bool
     */
    public function metaExists($key)
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        return Meta::metable(static::class, $this->id)
            ->where('key', $key)->exists();
    }

    /**
     * Count all meta
     * related to this model (via metable)
     * for package realm
     *
     * @return int
     */
    public function countMeta()
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        return Meta::metable(static::class, $this->id)
            ->count();
    }

    /**
     * Get all meta
     * related to this model (via metable)
     * for package realm
     *
     * @return array
     */
    public function allMeta()
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        $meta = Meta::metable(static::class, $this->id)
            ->get(['key', 'value', 'type']);

        $data = [];
        foreach ($meta as $m) {
            $data[$m->key] = $m->value;
        }

        return $data;
    }

    /**
     * Get all meta keys
     * related to this model (via metable)
     * for package realm
     *
     * @return array
     */
    public function metaKeys()
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        return Meta::metable(static::class, $this->id)
            ->pluck('key')
            ->toArray();
    }

    /**
     * Remove meta at given key or array of keys
     * related to this model (via metable)
     * for package realm
     *
     * @param string|array $key
     */
    public function removeMeta($key)
    {
        $keys = (array) $key;

        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        Meta::metable(static::class, $this->id)
            ->whereIn('key', $keys)
            ->delete();
    }

    /**
     * Purge meta
     * related to this model (via metable)
     * for package realm
     *
     * @return int Number of records deleted
     */
    public function purgeMeta()
    {
        /** @var \Vkovic\LaravelModelMeta\Models\Interfaces\HasMetadataInterface; $this */
        return Meta::metable(static::class, $this->id)
            ->delete();
    }

    //
    // Scopes
    //

    /**
     * Filter all models by providing meta data
     *
     * @param Builder    $query
     * @param string     $key
     * @param string     $operator
     * @param null|mixed $value
     *
     * @return Builder
     *
     * @throws \Exception
     */
    public function scopeWhereMeta(Builder $query, $key, $operator, $value = null)
    {
        // If there is no value, it means operator is value
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        // Prevent invalid operators
        $validOperators = ['<', '<=', '>', '>=', '=', '<>', '!='];
        if (!in_array($operator, $validOperators)) {
            throw new \Exception('Invalid operator. Allowed: ' . implode(', ', $validOperators));
        }

        // Convert array to json for raw comparison
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return $query->whereHas('meta', function (Builder $q) use ($key, $operator, $value) {
            $q->where('key', $key);

            // In case we're using compare operators, we need to perform casting because
            // all our values are written as string in database.
            if (strpos($operator, '<') !== false || strpos($operator, '>') !== false) {
                $q->where(\DB::raw("CAST(`value` AS UNSIGNED)"), $operator, $value);
            } else {
                $q->where('key', $key)->where('value', $operator, $value);
            }
        });
    }

    /**
     * Filter all models which meta contains given key
     *
     * @param Builder $query
     * @param string  $key
     *
     * @return Builder
     */
    public function scopeWhereHasMetaKey(Builder $query, $key)
    {
        return $query->whereHas('meta', function (Builder $q) use ($key) {
            $q->whereIn('key', (array) $key);
        });
    }
}