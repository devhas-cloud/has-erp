<?php

namespace App\Services;

use App\Models\User;

class MentionParser
{
    public static function extractMentionedIds(string $content): array
    {
        preg_match_all('/(?<=^|\s)@([a-zA-Z0-9_\.]+)/', $content, $matches);
        $usernames = array_unique($matches[1] ?? []);

        if (empty($usernames)) {
            return [];
        }

        return User::whereIn('username', $usernames)->pluck('id')->toArray();
    }
}
