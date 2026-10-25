import { useEffect, useState } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Paper,
  Stack,
  Typography,
} from '@mui/material'

import {
  completeMission,
  getMissions,
  startMission,
  type Mission,
  type MissionStatus,
} from '../../api/missions'
import type { AuthUser } from '../../api/auth'

const statusLabels: Record<MissionStatus, string> = {
  PENDING: 'En attente',
  IN_PROGRESS: 'En cours',
  COMPLETED: 'Terminée',
  CANCELLED: 'Annulée',
}

const statusColors: Record<
  MissionStatus,
  'default' | 'info' | 'success' | 'error'
> = {
  PENDING: 'default',
  IN_PROGRESS: 'info',
  COMPLETED: 'success',
  CANCELLED: 'error',
}

interface MissionListProps {
  user: AuthUser
}

/**
 * Display missions accessible to the authenticated user.
 */
export default function MissionList({ user }: MissionListProps) {
  const [missions, setMissions] = useState<Mission[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Track the mission currently being started to prevent duplicate requests.
  const [startingMissionId, setStartingMissionId] = useState<number | null>(null)

  // Track the mission currently being completed to prevent duplicate requests.
  const [completingMissionId, setCompletingMissionId] = useState<number | null>(null)

  useEffect(() => {
    let active = true

    async function loadMissions() {
      try {
        const response = await getMissions()

        if (active) {
          setMissions(response.data)
        }
      } catch (err) {
        if (!active) return

        if (err instanceof AxiosError && err.response?.status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else {
          setError('Impossible de charger les missions.')
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadMissions()

    return () => {
      active = false
    }
  }, [])

  /**
   * Start a pending mission assigned to the authenticated driver.
   *
   * Update the mission in local state after a successful API response
   * to refresh its status without reloading the page.
   */
  async function handleStartMission(id: number) {
    if (startingMissionId !== null) return

    setStartingMissionId(id)
    setError('')

    try {
      const updatedMission = await startMission(id)

      setMissions((currentMissions) =>
        currentMissions.map((mission) =>
          mission.id === id ? updatedMission : mission
        )
      )
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à démarrer cette mission.")
      } else if (err instanceof AxiosError && err.response?.status === 409) {
        setError('Cette mission ne peut plus être démarrée.')
      } else {
        setError('Impossible de démarrer la mission.')
      }
    } finally {
      setStartingMissionId(null)
    }
  }

  /**
   * Complete an in-progress mission assigned to the authenticated driver.
   *
   * Update the mission in local state after a successful API response
   * to refresh its status without reloading the page.
   */
  async function handleCompleteMission(id: number) {
    if (startingMissionId !== null || completingMissionId !== null) return

    setCompletingMissionId(id)
    setError('')

    try {
      const updatedMission = await completeMission(id)

      setMissions((currentMissions) =>
        currentMissions.map((mission) =>
          mission.id === id ? updatedMission : mission
        )
      )
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à terminer cette mission.")
      } else if (err instanceof AxiosError && err.response?.status === 409) {
        setError('Cette mission ne peut plus être terminée.')
      } else {
        setError('Impossible de terminer la mission.')
      }
    } finally {
      setCompletingMissionId(null)
    }
  }

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
        <CircularProgress aria-label="Chargement des missions" />
      </Box>
    )
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h6" component="h2">
        Mes missions
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}

      {!error && missions.length === 0 && (
        <Alert severity="info">
          Aucune mission à afficher pour le moment.
        </Alert>
      )}

      {!error &&
        missions.map((mission) => (
          <Paper
            key={mission.id}
            variant="outlined"
            sx={{ p: 2.5, borderRadius: 2 }}
          >
            <Stack spacing={1.5}>
              <Box
                sx={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  flexWrap: 'wrap',
                  gap: 1,
                }}
              >
                <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                  {mission.title}
                </Typography>

                <Chip
                  label={statusLabels[mission.status]}
                  color={statusColors[mission.status]}
                  size="small"
                />
              </Box>

              <Typography variant="body2" color="text.secondary">
                Référence : {mission.reference}
              </Typography>

              <Typography variant="body2">
                Départ : {mission.pickup_address}
              </Typography>

              <Typography variant="body2">
                Arrivée : {mission.delivery_address}
              </Typography>

              <Typography variant="body2" color="text.secondary">
                Chauffeur : {mission.driver?.name ?? 'Non affecté'}
              </Typography>

              {user.role === 'DRIVER' &&
                mission.driver_id === user.id &&
                (mission.status === 'PENDING' ||
                  mission.status === 'IN_PROGRESS') && (
                  <Box sx={{ display: 'flex', justifyContent: 'flex-end', pt: 1 }}>
                    {mission.status === 'PENDING' && (
                      <Button
                        variant="contained"
                        disabled={
                          startingMissionId !== null ||
                          completingMissionId !== null
                        }
                        loading={startingMissionId === mission.id}
                        onClick={() => void handleStartMission(mission.id)}
                      >
                        Démarrer la mission
                      </Button>
                    )}

                    {mission.status === 'IN_PROGRESS' && (
                      <Button
                        variant="contained"
                        color="success"
                        disabled={
                          startingMissionId !== null ||
                          completingMissionId !== null
                        }
                        loading={completingMissionId === mission.id}
                        onClick={() => void handleCompleteMission(mission.id)}
                      >
                        Terminer la mission
                      </Button>
                    )}
                  </Box>
                )}
            </Stack>
          </Paper>
        ))}
    </Stack>
  )
}