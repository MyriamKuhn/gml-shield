
import { useEffect, useState, type FormEventHandler } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  FormControl,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  Stack,
  Typography,
} from '@mui/material'
import { getDrivers, type Driver } from '../../api/drivers'
import { assignMission, type Mission } from '../../api/missions'

interface MissionAssignFormProps {
  mission: Mission
  onMissionAssigned: (mission: Mission) => void
}

/**
 * Display a driver assignment form for a pending mission.
 *
 * Driver options are retrieved from the Laravel API.
 * The backend validates the selected driver and enforces permissions.
 */
export default function MissionAssignForm({
  mission,
  onMissionAssigned,
}: MissionAssignFormProps) {
  const [drivers, setDrivers] = useState<Driver[]>([])
  const [driverId, setDriverId] = useState('')
  const [loadingDrivers, setLoadingDrivers] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true

    /**
     * Load the drivers available for assignment.
     */
    async function loadDrivers() {
      try {
        const availableDrivers = await getDrivers()

        if (active) {
          setDrivers(availableDrivers)
        }
      } catch (err) {
        if (!active) return

        if (err instanceof AxiosError && err.response?.status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else if (err instanceof AxiosError && err.response?.status === 403) {
          setError("Vous n'êtes pas autorisé à consulter les chauffeurs.")
        } else {
          setError('Impossible de charger la liste des chauffeurs.')
        }
      } finally {
        if (active) {
          setLoadingDrivers(false)
        }
      }
    }

    void loadDrivers()

    return () => {
      active = false
    }
  }, [])

  /**
   * Assign the selected driver to the mission.
   *
   * Notify the parent component after a successful API response
   * so the mission list can display the updated assignment.
   */
  const handleSubmit: FormEventHandler<HTMLFormElement> = (event) => {
    event.preventDefault()

    if (submitting || loadingDrivers || !driverId) return

    setSubmitting(true)
    setError('')

    void (async () => {
      try {
        const updatedMission = await assignMission(
          mission.id,
          Number(driverId)
        )

        onMissionAssigned(updatedMission)
        setDriverId('')
      } catch (err) {
        if (err instanceof AxiosError && err.response?.status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else if (err instanceof AxiosError && err.response?.status === 403) {
          setError("Vous n'êtes pas autorisé à affecter cette mission.")
        } else if (err instanceof AxiosError && err.response?.status === 422) {
          setError('Le chauffeur sélectionné est invalide.')
        } else if (err instanceof AxiosError && err.response?.status === 409) {
          setError('Cette mission ne peut plus être affectée.')
        } else {
          setError("Impossible d'affecter le chauffeur.")
        }
      } finally {
        setSubmitting(false)
      }
    })()
  }

  return (
    <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 2 }}>
      <Stack component="form" spacing={2} onSubmit={handleSubmit}>
        <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
          Affecter un chauffeur
        </Typography>

        <Typography variant="body2" color="text.secondary">
          Mission : {mission.reference} — {mission.title}
        </Typography>

        {error && <Alert severity="error">{error}</Alert>}

        {loadingDrivers ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 2 }}>
            <CircularProgress
              size={24}
              aria-label="Chargement des chauffeurs"
            />
          </Box>
        ) : drivers.length === 0 ? (
          <Alert severity="info">
            Aucun chauffeur disponible dans la liste.
          </Alert>
        ) : (
          <FormControl fullWidth required disabled={submitting}>
            <InputLabel id={`driver-label-${mission.id}`}>
              Chauffeur
            </InputLabel>

            <Select
              labelId={`driver-label-${mission.id}`}
              id={`driver-select-${mission.id}`}
              value={driverId}
              label="Chauffeur"
              onChange={(event) => setDriverId(event.target.value)}
            >
              {drivers.map((driver) => (
                <MenuItem key={driver.id} value={String(driver.id)}>
                  {driver.name} — {driver.email}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        )}

        <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
          <Button
            type="submit"
            variant="contained"
            disabled={
              submitting ||
              loadingDrivers ||
              drivers.length === 0 ||
              !driverId
            }
            loading={submitting}
          >
            Affecter le chauffeur
          </Button>
        </Box>
      </Stack>
    </Paper>
  )
}
