import { useState } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import {
  createMission,
  type CreateMissionData,
  type Mission,
} from '../../api/missions'

interface MissionCreateFormProps {
  onMissionCreated: (mission: Mission) => void
}

/**
 * Display a mission creation form for authorized dispatchers and admins.
 *
 * Laravel remains responsible for validating the submitted data
 * and checking the authenticated user's permissions.
 */
export default function MissionCreateForm({
  onMissionCreated,
}: MissionCreateFormProps) {
  const [reference, setReference] = useState('')
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [pickupAddress, setPickupAddress] = useState('')
  const [deliveryAddress, setDeliveryAddress] = useState('')
  const [scheduledAt, setScheduledAt] = useState('')
  const [secretCode, setSecretCode] = useState('')

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  /**
   * Submit a new mission to the Laravel API.
   *
   * Convert the local date and time to ISO 8601 before submission.
   * The backend assigns the initial PENDING status automatically.
   */
  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (submitting) return

    setError('')
    setSuccess('')

    const scheduledDate = new Date(scheduledAt)

    if (
      !scheduledAt ||
      Number.isNaN(scheduledDate.getTime()) ||
      scheduledDate.getTime() <= Date.now()
    ) {
      setError('La date prévue doit être dans le futur.')
      return
    }

    const data: CreateMissionData = {
      reference: reference.trim(),
      title: title.trim(),
      description: description.trim() || null,
      pickup_address: pickupAddress.trim(),
      delivery_address: deliveryAddress.trim(),
      scheduled_at: scheduledDate.toISOString(),
      secret_code: secretCode.trim(),
    }

    if (
      !data.reference ||
      !data.title ||
      !data.pickup_address ||
      !data.delivery_address ||
      !data.secret_code
    ) {
      setError('Veuillez renseigner tous les champs obligatoires.')
      return
    }

    setSubmitting(true)

    try {
      const mission = await createMission(data)

      setSuccess(`La mission ${mission.reference} a été créée avec succès.`)

      // Reset the form after successful mission creation.
      setReference('')
      setTitle('')
      setDescription('')
      setPickupAddress('')
      setDeliveryAddress('')
      setScheduledAt('')
      setSecretCode('')

      // Notify the parent component about the newly created mission.
      onMissionCreated(mission)
    } catch (err) {
      if (err instanceof AxiosError) {
        const status = err.response?.status

        if (status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else if (status === 403) {
          setError("Vous n'êtes pas autorisé à créer une mission.")
        } else if (status === 422) {
          setError(
            'Les données saisies sont invalides. Vérifiez notamment la référence et la date prévue.'
          )
        } else {
          setError('Impossible de créer la mission.')
        }
      } else {
        setError('Une erreur inattendue est survenue.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Paper variant="outlined" sx={{ p: 3, borderRadius: 2 }}>
      <Stack
        component="form"
        spacing={2.5}
        onSubmit={(event) => void handleSubmit(event)}
      >
        <Typography variant="h6" component="h2">
          Créer une mission
        </Typography>

        <Typography variant="body2" color="text.secondary">
          Renseignez les informations de la mission. L'affectation à un
          chauffeur sera effectuée séparément.
        </Typography>

        {error && <Alert severity="error">{error}</Alert>}

        {success && <Alert severity="success">{success}</Alert>}

        <TextField
          label="Référence"
          value={reference}
          onChange={(event) => setReference(event.target.value)}
          required
          slotProps={{
            htmlInput: { maxLength: 50 },
          }}
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Titre de la mission"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          required
          slotProps={{
            htmlInput: { maxLength: 150 },
          }}
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Description"
          value={description}
          onChange={(event) => setDescription(event.target.value)}
          multiline
          rows={3}
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Adresse de départ"
          value={pickupAddress}
          onChange={(event) => setPickupAddress(event.target.value)}
          required
          slotProps={{
            htmlInput: { maxLength: 500 },
          }}
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Adresse de livraison"
          value={deliveryAddress}
          onChange={(event) => setDeliveryAddress(event.target.value)}
          required
          slotProps={{
            htmlInput: { maxLength: 500 },
          }}
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Code secret d'accès à l'entrepôt"
          type="password"
          value={secretCode}
          onChange={(event) => setSecretCode(event.target.value)}
          required
          slotProps={{
            htmlInput: {
              maxLength: 255,
              autoComplete: 'off',
              spellCheck: false,
            },
          }}
          fullWidth
          disabled={submitting}
          helperText="Ce code sera chiffré avant son enregistrement."
        />

        <TextField
          label="Date et heure prévues"
          type="datetime-local"
          value={scheduledAt}
          onChange={(event) => setScheduledAt(event.target.value)}
          slotProps={{
            inputLabel: { shrink: true },
          }}
          required
          fullWidth
          disabled={submitting}
          helperText="La date et l'heure doivent être dans le futur."
        />

        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
          <Button
            type="submit"
            variant="contained"
            disabled={submitting}
            startIcon={
              submitting ? <CircularProgress size={18} color="inherit" /> : null
            }
          >
            {submitting ? 'Création en cours...' : 'Créer la mission'}
          </Button>
        </Box>
      </Stack>
    </Paper>
  )
}