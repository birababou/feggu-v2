<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalController extends Controller
{
    public function __invoke(Request $request)
    {
        $filtres = $request->only(['q', 'action']);

        return Inertia::render('Admin/Journal', [
            'filtres' => $filtres,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entrees' => AuditLog::with('user:id,name')
                ->when($filtres['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('ip', 'like', "%$v%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%"))))
                ->when($filtres['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
                ->latest('id')->paginate(40)->withQueryString()->through(fn ($a) => [
                'id' => $a->id, 'date' => $a->created_at?->format('d/m/Y H:i:s'), 'utilisateur' => $a->user?->name ?? '—',
                'action' => $a->action, 'cible' => $a->cible_type ? $a->cible_type.' #'.$a->cible_id : null, 'ip' => config('feggu.demo') ? 'masquée (démo)' : $a->ip,
            ]),
        ]);
    }
}
