import { useState, type ChangeEvent } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Stack,
  Typography,
} from '@mui/material'
import { uploadDeliveryDocument } from '../../api/documents'

interface MissionDocumentUploadProps {
  missionId: number
  onUploadSuccess: () => void
}

/**
 * Allow the assigned driver to submit a delivery proof.
 *
 * Server-side authorization and file validation remain
 * mandatory regardless of frontend restrictions.
 */
export default function MissionDocumentUpload({
  missionId,
  onUploadSuccess,
}: MissionDocumentUploadProps) {
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  function handleFileChange(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0] ?? null

    setSelectedFile(file)
    setError('')
    setSuccess('')
  }

  async function handleUpload() {
    if (!selectedFile || uploading) return

    if (selectedFile.size > 10 * 1024 * 1024) {
      setError('Le fichier ne doit pas dépasser 10 Mo.')
      return
    }

    setUploading(true)
    setError('')
    setSuccess('')

    try {
      await uploadDeliveryDocument(missionId, selectedFile)

      onUploadSuccess()

      setSelectedFile(null)
      setSuccess('La preuve de livraison a été enregistrée.')
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à déposer cette preuve.")
      } else if (err instanceof AxiosError && err.response?.status === 413) {
        setError('Le fichier dépasse la taille autorisée.')
      } else if (err instanceof AxiosError && err.response?.status === 422) {
        setError('Fichier invalide. Utilisez un PDF, JPEG ou PNG de 10 Mo maximum.')
      } else {
        setError("Impossible d'envoyer la preuve de livraison.")
      }
    } finally {
      setUploading(false)
    }
  }

  return (
    <Stack spacing={1.5}>
      <Typography variant="subtitle2">
        Preuve de livraison
      </Typography>

      <Box>
        <Button
          component="label"
          variant="outlined"
          disabled={uploading}
        >
          Choisir un fichier
          <input
            type="file"
            hidden
            accept=".pdf,.jpg,.jpeg,.png"
            onChange={handleFileChange}
            disabled={uploading}
          />
        </Button>
      </Box>

      {selectedFile && (
        <Typography variant="body2" color="text.secondary">
          Fichier sélectionné : {selectedFile.name}
        </Typography>
      )}

      <Button
        variant="contained"
        onClick={() => void handleUpload()}
        disabled={!selectedFile || uploading}
        startIcon={uploading ? <CircularProgress size={16} /> : undefined}
      >
        {uploading ? 'Envoi en cours...' : 'Envoyer la preuve'}
      </Button>

      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}
    </Stack>
  )
}
