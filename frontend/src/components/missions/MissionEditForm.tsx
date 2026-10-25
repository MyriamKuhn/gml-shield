
import { useState, type SubmitEvent } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import {
  updateMission,
  type Mission,
  type UpdateMissionData,
} from '../../api/missions'

interface MissionEditFormProps {
  mission: Mission
  onMissionUpdated: (mission: Mission) => void
  onCancel: () => void
}

/**
 * Display an edit form for a pending mission.
 *
 * The backend validates editable fields and verifies that
 * the authenticated user is authorized to update the mission.
 */
export default function MissionEditForm({
  mission,
  onMissionUpdated,
  onCancel,
}: MissionEditFormProps) {
  const [reference, setReference] = useState(mission.reference)
  const [title, setTitle] = useState(mission.title)
  const [description, setDescription] = useState(mission.description ?? '')
  const [pickupAddress, setPickupAddress] = useState(mission.pickup_address)
  const [deliveryAddress, setDeliveryAddress] = useState(
    mission.delivery_address
  )
  const [scheduledAt, setScheduledAt] = useState(() => {
    const date = new Date(mission.scheduled_at)

    if (Number.isNaN(date.getTime())) return ''

    // Convert the API timestamp to local time for datetime-local.
    const offset = date.getTimezoneOffset() * 60_000

    return new Date(date.getTime() - offset).toISOString().slice(0, 16)
  })

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  /**
   * Submit updated mission information to the Laravel API.
   *
   * Convert the local scheduled time to ISO 8601
   * and notify the parent component after a successful update.
   */
  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (submitting) return

    setError('')

    const scheduledDate = new Date(scheduledAt)

    if (
      !scheduledAt ||
      Number.isNaN(scheduledDate.getTime()) ||
      scheduledDate.getTime() <= Date.now()
    ) {
      setError('La date prévue doit être dans le futur.')
      return
    }

    const data: UpdateMissionData = {
      reference: reference.trim(),
      title: title.trim(),
      description: description.trim() || null,
      pickup_address: pickupAddress.trim(),
      delivery_address: deliveryAddress.trim(),
      scheduled_at: scheduledDate.toISOString(),
    }

    if (
      !data.reference ||
      !data.title ||
      !data.pickup_address ||
      !data.delivery_address
    ) {
      setError('Veuillez renseigner tous les champs obligatoires.')
      return
    }

    setSubmitting(true)

    try {
      const updatedMission = await updateMission(mission.id, data)
      onMissionUpdated(updatedMission)
    } catch (err) {
      if (err instanceof AxiosError) {
        const status = err.response?.status

        if (status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else if (status === 403) {
          setError("Vous n'êtes pas autorisé à modifier cette mission.")
        } else if (status === 422) {
          setError(
            'Les informations sont invalides. Vérifiez notamment la référence et la date prévue.'
          )
        } else {
          setError('Impossible de modifier la mission.')
        }
      } else {
        setError('Une erreur inattendue est survenue.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 2 }}>
      <Stack
        component="form"
        spacing={2}
        onSubmit={(event) => void handleSubmit(event)}
      >
        <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
          Modifier la mission
        </Typography>

        {error && <Alert severity="error">{error}</Alert>}

        <TextField
          label="Référence"
          value={reference}
          onChange={(event) => setReference(event.target.value)}
          slotProps={{ htmlInput: { maxLength: 50 } }}
          required
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Titre de la mission"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          slotProps={{ htmlInput: { maxLength: 150 } }}
          required
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
          slotProps={{ htmlInput: { maxLength: 500 } }}
          required
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Adresse de livraison"
          value={deliveryAddress}
          onChange={(event) => setDeliveryAddress(event.target.value)}
          slotProps={{ htmlInput: { maxLength: 500 } }}
          required
          fullWidth
          disabled={submitting}
        />

        <TextField
          label="Date et heure prévues"
          type="datetime-local"
          value={scheduledAt}
          onChange={(event) => setScheduledAt(event.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
          required
          fullWidth
          disabled={submitting}
          helperText="La date et l'heure doivent être dans le futur."
        />

        <Box
          sx={{
            display: 'flex',
            justifyContent: 'flex-end',
            flexWrap: 'wrap',
            gap: 1,
          }}
        >
          <Button
            variant="outlined"
            onClick={onCancel}
            disabled={submitting}
          >
            Annuler les modifications
          </Button>

          <Button
            type="submit"
            variant="contained"
            loading={submitting}
            disabled={submitting}
          >
            Enregistrer les modifications
          </Button>
        </Box>
      </Stack>
    </Paper>
  )
}
