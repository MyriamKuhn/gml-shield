
import { useState, type SubmitEvent } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Divider,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'

import AddCircleOutlineRoundedIcon from '@mui/icons-material/AddCircleOutlineRounded'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import RouteOutlinedIcon from '@mui/icons-material/RouteOutlined'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import EventOutlinedIcon from '@mui/icons-material/EventOutlined'

import {
  createMission,
  type CreateMissionData,
  type Mission,
} from '../../api/missions'

interface MissionCreateFormProps {
  onMissionCreated: (mission: Mission) => void
}

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
  const [formVersion, setFormVersion] = useState(0)

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
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

      setSuccess(
        `La mission ${mission.reference} a été créée avec succès.`,
      )

      setReference('')
      setTitle('')
      setDescription('')
      setPickupAddress('')
      setDeliveryAddress('')
      setScheduledAt('')
      setSecretCode('')

      // Reset only the form's DOM after successful creation.
      setFormVersion((current) => current + 1)

      // The parent will refresh the mission list.
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
            'Les données saisies sont invalides. Vérifiez notamment la référence et la date prévue.',
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
    <Paper
      variant="outlined"
      sx={{
        p: { xs: 2, sm: 3 },
        borderRadius: 2.5,
        bgcolor: '#FFFFFF',
      }}
    >
      <Stack spacing={3}>
        {/* Heading */}
        <Box
          sx={{
            display: 'flex',
            alignItems: 'center',
            gap: 2,
          }}
        >
          <Box
            sx={{
              width: 48,
              height: 48,
              borderRadius: 2,
              bgcolor: '#E8F5F2',
              color: '#00897B',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
            }}
          >
            <AddCircleOutlineRoundedIcon sx={{ fontSize: 27 }} />
          </Box>

          <Box>
            <Typography
              variant="h6"
              component="h3"
              sx={{ fontWeight: 700 }}
            >
              Créer une mission
            </Typography>

            <Typography variant="body2" color="text.secondary">
              Préparez une nouvelle mission de transport.
            </Typography>
          </Box>
        </Box>

        <Typography variant="body2" color="text.secondary">
          La mission sera créée avec le statut « En attente ».
          L'affectation à un chauffeur sera effectuée séparément.
        </Typography>

        {error && <Alert severity="error">{error}</Alert>}
        {success && <Alert severity="success">{success}</Alert>}

        <Box
          key={formVersion}
          component="form"
          onSubmit={(event) => void handleSubmit(event)}
          autoComplete="off"
        >
          <Stack spacing={3}>
            {/* General information */}
            <Stack spacing={2}>
              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 1.5,
                }}
              >
                <LocalShippingOutlinedIcon
                  sx={{ color: '#00897B' }}
                />

                <Typography
                  variant="subtitle1"
                  sx={{ fontWeight: 700 }}
                >
                  Informations générales
                </Typography>
              </Box>

              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: {
                    xs: '1fr',
                    md: 'repeat(2, minmax(0, 1fr))',
                  },
                  gap: 2,
                }}
              >
                <TextField
                  label="Référence de mission"
                  name="gml-mission-reference"
                  value={reference}
                  onChange={(event) => setReference(event.target.value)}
                  required
                  fullWidth
                  disabled={submitting}
                  autoComplete="off"
                  slotProps={{
                    htmlInput: { maxLength: 50 },
                  }}
                />

                <TextField
                  label="Titre de la mission"
                  name="gml-mission-title"
                  value={title}
                  onChange={(event) => setTitle(event.target.value)}
                  required
                  fullWidth
                  disabled={submitting}
                  autoComplete="off"
                  slotProps={{
                    htmlInput: { maxLength: 150 },
                  }}
                />
              </Box>

              <TextField
                label="Description"
                name="gml-mission-description"
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                multiline
                rows={3}
                fullWidth
                disabled={submitting}
                autoComplete="off"
                helperText="Facultatif — précisez les instructions utiles au chauffeur."
              />
            </Stack>

            <Divider />

            {/* Route */}
            <Stack spacing={2}>
              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 1.5,
                }}
              >
                <RouteOutlinedIcon sx={{ color: '#00897B' }} />

                <Typography
                  variant="subtitle1"
                  sx={{ fontWeight: 700 }}
                >
                  Trajet
                </Typography>
              </Box>

              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: {
                    xs: '1fr',
                    md: 'repeat(2, minmax(0, 1fr))',
                  },
                  gap: 2,
                }}
              >
                <TextField
                  label="Adresse de départ"
                  name="gml-mission-pickup-location"
                  value={pickupAddress}
                  onChange={(event) =>
                    setPickupAddress(event.target.value)
                  }
                  required
                  fullWidth
                  disabled={submitting}
                  autoComplete="off"
                  slotProps={{
                    htmlInput: { maxLength: 500 },
                  }}
                />

                <TextField
                  label="Adresse de livraison"
                  name="gml-mission-delivery-location"
                  value={deliveryAddress}
                  onChange={(event) =>
                    setDeliveryAddress(event.target.value)
                  }
                  required
                  fullWidth
                  disabled={submitting}
                  autoComplete="off"
                  slotProps={{
                    htmlInput: { maxLength: 500 },
                  }}
                />
              </Box>

              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 1.5,
                }}
              >
                <EventOutlinedIcon
                  sx={{ color: 'text.secondary', fontSize: 21 }}
                />

                <Typography
                  variant="body2"
                  sx={{ fontWeight: 600 }}
                >
                  Planification
                </Typography>
              </Box>

              <TextField
                label="Date et heure prévues"
                name="gml-mission-scheduled-at"
                type="datetime-local"
                value={scheduledAt}
                onChange={(event) => setScheduledAt(event.target.value)}
                required
                fullWidth
                disabled={submitting}
                slotProps={{
                  inputLabel: { shrink: true },
                }}
                helperText="La date et l'heure doivent être dans le futur."
              />
            </Stack>

            <Divider />

            {/* Sensitive information */}
            <Stack spacing={2}>
              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 1.5,
                }}
              >
                <LockOutlinedIcon sx={{ color: '#00897B' }} />

                <Typography
                  variant="subtitle1"
                  sx={{ fontWeight: 700 }}
                >
                  Informations sécurisées
                </Typography>
              </Box>

              <Box
                sx={{
                  bgcolor: '#F4F8FB',
                  border: '1px solid #E2EAF1',
                  borderRadius: 2,
                  p: { xs: 2, sm: 2.5 },
                }}
              >
                <Stack spacing={2}>
                  <Typography
                    variant="body2"
                    color="text.secondary"
                  >
                    Le code d'accès à l'entrepôt est une information
                    sensible. Il sera chiffré avant son enregistrement.
                  </Typography>

                  <TextField
                    label="Code secret d'accès à l'entrepôt"
                    type="password"
                    name="gml-mission-new-secret"
                    value={secretCode}
                    onChange={(event) =>
                      setSecretCode(event.target.value)
                    }
                    required
                    fullWidth
                    disabled={submitting}
                    autoComplete="new-password"
                    slotProps={{
                      htmlInput: {
                        maxLength: 255,
                        spellCheck: false,
                      },
                    }}
                    helperText="Ce code sera révélé uniquement au chauffeur autorisé, selon les règles de sécurité."
                  />
                </Stack>
              </Box>
            </Stack>

            <Box
              sx={{
                display: 'flex',
                justifyContent: 'flex-end',
                pt: 1,
              }}
            >
              <Button
                type="submit"
                variant="contained"
                disabled={submitting}
                startIcon={
                  submitting ? (
                    <CircularProgress size={18} color="inherit" />
                  ) : (
                    <AddCircleOutlineRoundedIcon />
                  )
                }
                sx={{
                  minHeight: 48,
                  px: 3,
                  width: { xs: '100%', sm: 'auto' },
                }}
              >
                {submitting
                  ? 'Création en cours...'
                  : 'Créer la mission'}
              </Button>
            </Box>
          </Stack>
        </Box>
      </Stack>
    </Paper>
  )
}
