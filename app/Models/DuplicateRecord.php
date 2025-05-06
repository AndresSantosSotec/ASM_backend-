<?php
// app/Models/DuplicateRecord.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateRecord extends Model
{
    protected $fillable = [
        'original_prospect_id',
        'duplicate_prospect_id',
        'similarity_score',
        'status',
    ];

    public function originalProspect()
    {
        return $this->belongsTo(Prospecto::class, 'original_prospect_id');
    }

    public function duplicateProspect()
    {
        return $this->belongsTo(Prospecto::class, 'duplicate_prospect_id');
    }
}
