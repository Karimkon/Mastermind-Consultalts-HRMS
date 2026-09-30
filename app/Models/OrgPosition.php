<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One box on the organisation chart.
 *
 * A position exists whether or not anybody holds it - that is the point of
 * keeping it apart from `employees.manager_id`. "General Manager" belongs on
 * the chart even while the post is vacant, and "Board of Directors" belongs on
 * it although no employee will ever fill it.
 */
class OrgPosition extends Model
{
    protected $fillable = ['client_id', 'parent_id', 'title', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function parent()   { return $this->belongsTo(self::class, 'parent_id'); }
    public function client()   { return $this->belongsTo(Client::class); }
    public function employees(){ return $this->hasMany(Employee::class, 'org_position_id'); }

    /** Direct reports, in the order somebody chose rather than by id. */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    /** The whole subtree in one go, for drawing the chart. */
    public function descendants()
    {
        return $this->children()->with(['descendants', 'employees.user', 'employees.department']);
    }

    /**
     * Walk up to the top, nearest parent first.
     *
     * Used for the "reports to" line on an employee, so a Roofings loader can be
     * shown their whole chain: Account Manager, HR and Operations, GM, MD, Board.
     */
    public function chainOfCommand(): array
    {
        $chain = [];
        $node  = $this->parent;
        // A parent_id that somehow pointed into a cycle would hang the page, so
        // the walk is bounded rather than trusting the data to be a tree.
        $guard = 0;
        while ($node && $guard++ < 20) {
            $chain[] = $node;
            $node = $node->parent;
        }
        return $chain;
    }
}
