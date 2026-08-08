<?php
class Admin
{
    public static function requireAdmin()
    {
        if (!Auth::check() || !Auth::isAdmin()) {
            redirect(base_url('admin/login.php'));
        }
    }

    public static function isAdmin()
    {
        return Auth::isAdmin();
    }
}