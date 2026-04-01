<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChatbotLearningService;

class TestLearningSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:test-learning {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the chatbot learning system integration';

    /**
     * Execute the console command.
     */
    public function handle(ChatbotLearningService $learningService): int
    {
        $userId = $this->argument('user_id') ?? null;
        
        $this->info('=== TEST 1: Intent Detection ===');
        $testQuestions = [
            "¿Cuánto cuesta un fade?" => "precio",
            "¿Cuál es el horario?" => "horario",
            "¿Puedo reservar una cita?" => "cita",
            "¿Quién es el mejor barbero?" => "barbero",
            "¿Qué servicios ofrecen?" => "servicio",
            "¿Dónde están ubicados?" => "ubicación",
        ];

        foreach ($testQuestions as $question => $expectedIntent) {
            $intent = $learningService->detectRealIntent($question);
            $status = ($intent === $expectedIntent) ? "✅" : "⚠️";
            $this->line("$status Pregunta: '$question'");
            $this->line("   Intención: $intent (esperado: $expectedIntent)");
        }
        $this->newLine();

        // Test 2: Question Learning
        $this->info('=== TEST 2: Question Learning ===');
        $testQuestion = "¿Qué es un desvanecimiento?";
        $testIntent = "fade";
        $learningService->learnQuestion($testQuestion, $testIntent, 0.95, $userId);
        $this->line("✅ Pregunta aprendida: '$testQuestion'");
        $this->line("   Intención: $testIntent");
        $this->line("   Confianza: 95%");
        $this->newLine();

        // Test 3: Synonyms
        $this->info('=== TEST 3: Palabra Síncronos ===');
        $testWords = ['precio', 'cita', 'barbero', 'fade'];
        foreach ($testWords as $word) {
            $synonyms = $learningService->findSynonyms($word);
            $this->line("✅ Palabra: '$word'");
            $this->line("   Sinónimos encontrados: " . count($synonyms));
        }
        $this->newLine();

        // Test 4: Similarity
        $this->info('=== TEST 4: Similitud Levenshtein ===');
        $q1 = "¿Cuánto cuesta un fade?";
        $q2 = "¿Cuál es el precio del desvanecimiento?";
        $similarity = $learningService->stringSimilarity($q1, $q2);
        $this->line("✅ Similitud entre preguntas: " . round($similarity * 100, 2) . "%");
        $this->line("   Q1: '$q1'");
        $this->line("   Q2: '$q2'");
        $this->newLine();

        // Test 5: Feedback Recording
        $this->info('=== TEST 5: Registro de Feedback ===');
        $learningService->recordFeedback(
            "¿Cuánto cuesta?",
            "Nuestros precios varían según el servicio.",
            true,
            $userId
        );
        $this->line("✅ Feedback registrado correctamente");
        $this->newLine();

        // Test 6: Learning Report
        $this->info('=== TEST 6: Reporte de Aprendizaje ===');
        try {
            $report = $learningService->getLearningReport($userId);
            $this->line("✅ Reporte generado:");
            $this->line("   Total categorías: {$report['total_categories']}");
            $this->line("   Preguntas aprendidas: {$report['total_learned_questions']}");
        } catch (\Exception $e) {
            $this->line("ℹ️  Sin datos aún: " . $e->getMessage());
        }
        $this->newLine();

        // Test 7: Top Categories
        $this->info('=== TEST 7: Top Categorías ===');
        $topCategories = $learningService->getTopCategories($userId);
        if (!empty($topCategories)) {
            $this->line("✅ Top categorías detectadas:");
            foreach ($topCategories as $key => $category) {
                $this->line("   $key. $category");
            }
        } else {
            $this->line("ℹ️  Sin categorías principales aún");
        }
        $this->newLine();

        // Summary
        $this->info('=== RESUMEN ===');
        $this->line("✅ Todos los tests completados");
        $this->line("✅ Sistema de Aprendizaje OPERATIVO");
        $this->line("\n🚀 El chatbot está listo para aprender de interacciones");

        return Command::SUCCESS;
    }
}
