<?php

namespace App\Models;

use App\Models\Base\HomeLinkContentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HomeGame extends HomeLinkContentModel
{
    use HasFactory;

    protected $table = 'home_games';
}
