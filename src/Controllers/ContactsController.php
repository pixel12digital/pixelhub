<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\ContactService;

/**
 * Controller para gerenciar contatos unificados (leads e clientes)
 */
class ContactsController extends Controller
{
    /**
     * Converte um lead em cliente
     * POST /contacts/convert-to-client
     */
    public function convertToClient(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $contactId = $input['contact_id'] ?? null;

        if (!$contactId) {
            $this->json(['success' => false, 'error' => 'ID do contato não fornecido'], 400);
            return;
        }

        try {
            // Busca o contato para verificar se é um lead
            $contact = ContactService::findById($contactId);
            if (!$contact) {
                $this->json(['success' => false, 'error' => 'Contato não encontrado'], 404);
                return;
            }

            if ($contact['contact_type'] !== 'lead') {
                $this->json(['success' => false, 'error' => 'Contato não é um lead'], 400);
                return;
            }

            // Converte o lead em cliente
            $result = ContactService::convertLeadToClient($contactId);

            if ($result) {
                $this->json([
                    'success' => true,
                    'message' => 'Lead convertido em cliente com sucesso',
                    'contact_id' => $contactId
                ]);
            } else {
                $this->json(['success' => false, 'error' => 'Falha ao converter lead'], 500);
            }
        } catch (\Exception $e) {
            error_log("[Contacts] Erro ao converter lead para cliente: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza dados de um contato (lead ou cliente)
     * POST /contacts/update
     */
    public function update(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $contactId = $input['contact_id'] ?? null;
        $data = $input['data'] ?? [];

        if (!$contactId) {
            $this->json(['success' => false, 'error' => 'ID do contato não fornecido'], 400);
            return;
        }

        try {
            // Busca o contato
            $contact = ContactService::findById($contactId);
            if (!$contact) {
                $this->json(['success' => false, 'error' => 'Contato não encontrado'], 404);
                return;
            }

            // Valida campos específicos por tipo
            if ($contact['contact_type'] === 'lead') {
                // Para leads, permite editar: name, phone, email, company, source, notes
                $allowedFields = ['name', 'phone', 'email', 'company', 'source', 'notes'];
                $filteredData = array_intersect_key($data, array_flip($allowedFields));
            } else {
                // Para clientes, permite editar campos padrão do tenant
                $allowedFields = ['name', 'phone', 'email', 'document', 'person_type'];
                $filteredData = array_intersect_key($data, array_flip($allowedFields));
            }

            if (empty($filteredData)) {
                $this->json(['success' => false, 'error' => 'Nenhum campo válido fornecido'], 400);
                return;
            }

            // Atualiza o contato
            ContactService::update($contactId, $filteredData);

            // Busca os dados atualizados
            $updatedContact = ContactService::findById($contactId);

            $this->json([
                'success' => true,
                'message' => 'Contato atualizado com sucesso',
                'contact' => $updatedContact
            ]);
        } catch (\Exception $e) {
            error_log("[Contacts] Erro ao atualizar contato: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca contatos para autocomplete
     * GET /contacts/search?q=termo&type=lead|client|all
     */
    public function search(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $query = trim($_GET['q'] ?? '');
        $type = $_GET['type'] ?? 'all';
        $limit = (int) ($_GET['limit'] ?? 20);

        if (strlen($query) < 2) {
            $this->json(['success' => true, 'contacts' => []]);
            return;
        }

        try {
            $contacts = ContactService::search($query, $type, $limit);
            $this->json(['success' => true, 'contacts' => $contacts]);
        } catch (\Exception $e) {
            error_log("[Contacts] Erro ao buscar contatos: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verifica duplicidade por telefone
     * GET /contacts/check-duplicate?phone=numero
     */
    public function checkDuplicate(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $phone = trim($_GET['phone'] ?? '');

        if (empty($phone)) {
            $this->json(['success' => true, 'duplicates' => []]);
            return;
        }

        try {
            $duplicates = ContactService::findDuplicatesByPhone($phone);
            $this->json([
                'success' => true,
                'duplicates' => $duplicates,
                'total' => count($duplicates)
            ]);
        } catch (\Exception $e) {
            error_log("[Contacts] Erro ao verificar duplicidade: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
