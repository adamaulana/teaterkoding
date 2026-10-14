<?php

namespace Tests\Feature;

use App\Models\ConceptCard;
use App\Models\Level;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use Database\Seeders\ConceptCardsSeeder;
use Database\Seeders\LevelsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_populate_reference_tables_idempotently(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(ConceptCardsSeeder::class);
        $this->seed(LevelsSeeder::class);

        $this->assertSame(5, Role::count());
        $this->assertSame(5, ConceptCard::count());
        $this->assertSame(4, Level::count());
        $this->assertTrue((bool) Level::where('level_number', 4)->first()?->is_summative);
        $this->assertFalse((bool) Level::where('level_number', 1)->first()?->is_summative);

        // Idempoten: dijalankan lagi tidak menambah baris.
        $this->seed(RolesSeeder::class);
        $this->seed(ConceptCardsSeeder::class);
        $this->seed(LevelsSeeder::class);

        $this->assertSame(5, Role::count());
        $this->assertSame(5, ConceptCard::count());
        $this->assertSame(4, Level::count());
    }

    public function test_models_persist_with_relations_and_score_ledger(): void
    {
        $class = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027']);
        $student = Student::create(['class_id' => $class->class_id, 'full_name' => 'Budi', 'student_no' => null]);
        $team = Team::create(['class_id' => $class->class_id, 'team_name' => 'Kelompok 3']);
        TeamMember::create(['team_id' => $team->team_id, 'student_id' => $student->student_id]);

        $this->assertTrue($class->students->contains($student));
        $this->assertTrue($team->students->contains($student));
        $this->assertSame($class->class_id, $team->schoolClass->class_id);
        $this->assertSame($student->student_id, $team->students->first()?->student_id);

        // Skor kelompok (tanpa student/mission/bug) dan skor individu negatif.
        $groupScore = ScoreEntry::create(['team_id' => $team->team_id, 'source_type' => 'plan_bonus', 'points' => 10]);
        $hintPenalty = ScoreEntry::create([
            'team_id' => $team->team_id,
            'student_id' => $student->student_id,
            'source_type' => 'hint_penalty',
            'points' => -5,
        ]);

        $groupScore->refresh();

        $this->assertNull($groupScore->student_id);
        $this->assertNull($groupScore->mission_id);
        $this->assertNull($groupScore->bug_id);
        $this->assertEquals(-5, $hintPenalty->points);
        $this->assertNotNull($groupScore->created_at);
        $this->assertEquals(5, $team->scoreEntries()->sum('points'));
        $this->assertEquals(-5, $student->scoreEntries()->sum('points'));
    }
}
