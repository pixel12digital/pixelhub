<?php

/**
 * Migration: Adiciona campos de transcrição de áudio na tabela communication_media
 * 
 * Permite armazenar transcrições de áudios do WhatsApp usando OpenAI Whisper API.
 * 
 * Campos adicionados:
 * - transcription: texto transcrito do áudio
 * - transcription_status: estado do processamento (pending, processing, completed, failed)
 * - transcription_error: mensagem de erro caso falhe
 * - transcription_at: timestamp de quando foi transcrito
 */
class AlterCommunicationMediaAddTranscription
{
    public function up(PDO $db): void
    {
        // Adiciona coluna transcription
        $db->exec("
            ALTER TABLE communication_media
            ADD COLUMN transcription TEXT NULL 
                COMMENT 'Texto transcrito do áudio (via OpenAI Whisper)' 
                AFTER file_size
        ");

        // Adiciona coluna transcription_status
        $db->exec("
            ALTER TABLE communication_media
            ADD COLUMN transcription_status ENUM('pending', 'processing', 'completed', 'failed') NULL 
                COMMENT 'Status da transcrição' 
                AFTER transcription
        ");

        // Adiciona coluna transcription_error
        $db->exec("
            ALTER TABLE communication_media
            ADD COLUMN transcription_error TEXT NULL 
                COMMENT 'Mensagem de erro se a transcrição falhar' 
                AFTER transcription_status
        ");

        // Adiciona coluna transcription_at
        $db->exec("
            ALTER TABLE communication_media
            ADD COLUMN transcription_at DATETIME NULL 
                COMMENT 'Data/hora em que a transcrição foi concluída' 
                AFTER transcription_error
        ");

        // Índice para buscar áudios pendentes de transcrição
        $db->exec("
            CREATE INDEX idx_transcription_status 
            ON communication_media (transcription_status)
        ");

        // Índice composto para buscar áudios de áudio pendentes (job de transcrição)
        $db->exec("
            CREATE INDEX idx_media_type_transcription 
            ON communication_media (media_type, transcription_status)
        ");
    }

    public function down(PDO $db): void
    {
        // Remove índices
        $db->exec("DROP INDEX idx_transcription_status ON communication_media");
        $db->exec("DROP INDEX idx_media_type_transcription ON communication_media");

        // Remove colunas
        $db->exec("ALTER TABLE communication_media DROP COLUMN transcription_at");
        $db->exec("ALTER TABLE communication_media DROP COLUMN transcription_error");
        $db->exec("ALTER TABLE communication_media DROP COLUMN transcription_status");
        $db->exec("ALTER TABLE communication_media DROP COLUMN transcription");
    }
}
