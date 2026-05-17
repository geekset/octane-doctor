<?php

namespace App\Services;

use Illuminate\Http\Request;

class ReportService
{
    public function __construct(
        protected Request $request,
    ) {}

    public function currentUserId(): ?int
    {
        return $this->request->user()?->id;
    }
}
