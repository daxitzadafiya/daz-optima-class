<?php

namespace Daz\OptimaClass\Components;

use Cocur\Slugify\Slugify;
use Daz\OptimaClass\Helpers\Cms;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

class Urlhelper
{
    public static function slug($tag)
    {
        return Cms::getSlugByTagName($tag);
    }

    public static function propertiesListingUrl()
    {
        return URL::to(self::slug('propertyList'));
    }

    public static function propertyDetailsSlug()
    {
        return self::slug('propertyDetails');
    }

    public static function developmentsListingUrl()
    {
        return URL::to(self::slug('developmentsListing'));
    }

    public static function developmentDetailsSlug()
    {
        return self::slug('developmentDetails');
    }

    public static function blogListingUrl()
    {
        return URL::to(self::slug('blogListing'));
    }

    public static function blogDetailsSlug()
    {
        return self::slug('blogDetails');   
    }

    /**
     * Urlhelper::getPropertyTitle($property, $language)
     * 
     * @param mixed $property
     * @param mixed $language
     * 
     * @return string
    */
    public static function getPropertyTitle($property, $language = '')
    {
        $lang = empty($language) ? strtoupper(App::getLocale()) : strtoupper($language);
        $slugify = new Slugify();
        $permaLink = isset($property['slug_all'][$lang]) ? $property['slug_all'][$lang] : 'property';

        return $slugify->slugify($permaLink) . '_' . $property['id'];
    }

    /**
     * Urlhelper::getPropertyUrl($property)
     * 
     * @param mixed $property
     * 
     * @return url
     */
    public static function getPropertyUrl($property)
    {
        return URL::to('/' . self::propertyDetailsSlug() . '/' . self::getPropertyTitle($property));
    }

    /**
     * Urlhelper::getDevelopmentTitle($development, $language)
     * 
     * @param mixed $development
     * @param mixed $language
     * 
     * @return string
     */
    public static function getDevelopmentTitle($development, $language = '')
    {
        $lang = empty($language) ? strtoupper(App::getLocale()) : strtoupper($language);
        $slugify = new Slugify();
        $permaLink = isset($development['slug_all'][$lang]) ? $development['slug_all'][$lang] : 'property';

        return $slugify->slugify($permaLink) . '_' . $development['id'];
    }

    /**
     * Urlhelper::getDevelopmentUrl($development, $language)
     * 
     * @param mixed $development
     * 
     * @return url
     */
    public static function getDevelopmentUrl($development)
    {
        return URL::to('/' . self::developmentDetailsSlug() . '/' . self::getDevelopmentTitle($development));
    }

    /**
     * Urlhelper::getPostTitle($post, $language)
     * 
     * @param mixed $post
     * @param mixed $language
     * 
     * @return string
     */
    public static function getPostTitle($post, $language = '')
    {
        $lang = empty($language) ? strtoupper(App::getLocale()) : strtoupper($language);
        $slugify = new Slugify();
        $postSlug = isset($post['slug_all'][$lang]) ? $post['slug_all'][$lang] : 'post-not-found';

        return $slugify->slugify($postSlug);
    }

    /**
     * Urlhelper::getBlogUrl($development, $language)
     * 
     * @param mixed $post
     * @param string $language
     * 
     * @return url
     */
    public static function getBlogUrl($post)
    {
        return URL::to('/' . self::blogDetailsSlug() . '/' . self::getPostTitle($post));
    }
}