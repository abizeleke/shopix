<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use Throwable;

class NotesController extends BaseController
{
    use ResponseTrait;

    /**
     * Get the authenticated user's ID.
     */
    private function getUserId(): ?int
    {
        $userId = $this->request->userId ?? null;

        if ($userId === null) {
            return null;
        }

        $userId = (int) $userId;

        return $userId > 0 ? $userId : null;
    }

    /**
     * GET /api/notes
     *
     * Returns notes belonging to the logged-in user.
     */
    public function index()
    {
        try {
            $userId = $this->getUserId();

            if ($userId === null) {
                return $this->failUnauthorized(
                    'Could not determine the logged-in user.'
                );
            }

            $db = Database::connect();

            $notes = $db->table('notes')
                ->where('user_id', $userId)
                ->orderBy('updated_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray();

            return $this->respond([
                'success' => true,
                'notes'   => $notes,
                'count'   => count($notes),
            ]);
        } catch (Throwable $e) {
            return $this->failServerError(
                'Failed to load notes: ' . $e->getMessage()
            );
        }
    }

    /**
     * POST /api/notes
     *
     * Create a note for the logged-in user.
     */
    public function create()
    {
        try {
            $userId = $this->getUserId();

            if ($userId === null) {
                return $this->failUnauthorized(
                    'Could not determine the logged-in user.'
                );
            }

            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->failValidationErrors([
                    'request' => 'Invalid JSON request.'
                ]);
            }

            $title = trim((string) ($data['title'] ?? ''));
            $content = trim((string) ($data['content'] ?? ''));

            if ($content === '') {
                return $this->failValidationErrors([
                    'content' => 'Note content is required.'
                ]);
            }

            if ($title === '') {
                $title = null;
            }

            $db = Database::connect();

            $insertData = [
                'user_id'    => $userId,
                'title'      => $title,
                'content'    => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $db->table('notes')->insert($insertData);

            $noteId = $db->insertID();

            $note = $db->table('notes')
                ->where('id', $noteId)
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            return $this->respondCreated([
                'success' => true,
                'message' => 'Note created successfully.',
                'note'    => $note,
            ]);
        } catch (Throwable $e) {
            return $this->failServerError(
                'Failed to create note: ' . $e->getMessage()
            );
        }
    }

    /**
     * PUT /api/notes/{id}
     *
     * Update a note belonging to the logged-in user.
     */
    public function update($id)
    {
        try {
            $userId = $this->getUserId();

            if ($userId === null) {
                return $this->failUnauthorized(
                    'Could not determine the logged-in user.'
                );
            }

            $id = (int) $id;

            if ($id <= 0) {
                return $this->failValidationErrors([
                    'id' => 'Invalid note ID.'
                ]);
            }

            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->failValidationErrors([
                    'request' => 'Invalid JSON request.'
                ]);
            }

            $title = trim((string) ($data['title'] ?? ''));
            $content = trim((string) ($data['content'] ?? ''));

            if ($content === '') {
                return $this->failValidationErrors([
                    'content' => 'Note content is required.'
                ]);
            }

            if ($title === '') {
                $title = null;
            }

            $db = Database::connect();

            // Make sure the note belongs to this logged-in user.
            $existing = $db->table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            if (!$existing) {
                return $this->failNotFound(
                    'Note not found.'
                );
            }

            $db->table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->update([
                    'title'      => $title,
                    'content'    => $content,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $note = $db->table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            return $this->respond([
                'success' => true,
                'message' => 'Note updated successfully.',
                'note'    => $note,
            ]);
        } catch (Throwable $e) {
            return $this->failServerError(
                'Failed to update note: ' . $e->getMessage()
            );
        }
    }

    /**
     * DELETE /api/notes/{id}
     *
     * Delete a note belonging to the logged-in user.
     */
    public function delete($id)
    {
        try {
            $userId = $this->getUserId();

            if ($userId === null) {
                return $this->failUnauthorized(
                    'Could not determine the logged-in user.'
                );
            }

            $id = (int) $id;

            if ($id <= 0) {
                return $this->failValidationErrors([
                    'id' => 'Invalid note ID.'
                ]);
            }

            $db = Database::connect();

            $existing = $db->table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            if (!$existing) {
                return $this->failNotFound(
                    'Note not found.'
                );
            }

            $db->table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            return $this->respond([
                'success' => true,
                'message' => 'Note deleted successfully.',
            ]);
        } catch (Throwable $e) {
            return $this->failServerError(
                'Failed to delete note: ' . $e->getMessage()
            );
        }
    }
}