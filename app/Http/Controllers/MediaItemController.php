<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use Illuminate\View\View;

class MediaItemController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MediaItem::class);

        return view('media-items.index');
    }
}
