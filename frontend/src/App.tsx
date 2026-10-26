
import { useEffect, useState, type SubmitEvent } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Container,
  Divider,
  Paper,
  Snackbar,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import VerifiedUserOutlinedIcon from '@mui/icons-material/VerifiedUserOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import RouteOutlinedIcon from '@mui/icons-material/RouteOutlined'
import ArrowForwardRoundedIcon from '@mui/icons-material/ArrowForwardRounded'
import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'

import { getCurrentUser, login, logout, type AuthUser } from './api/auth'
import MissionList from './components/missions/MissionList'
import AdminDashboard from './components/admin/AdminDashboard'
import DispatcherDashboard from './components/dispatcher/DispatcherDashboard'
import echo from './lib/echo'

function App() {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [checkingSession, setCheckingSession] = useState(true)
  const [error, setError] = useState('')

  const [missionRefreshKey, setMissionRefreshKey] = useState(0)
  const [missionNotification, setMissionNotification] = useState('')

  // Restore the existing authenticated session.
  useEffect(() => {
    let active = true

    getCurrentUser()
      .then((currentUser) => {
        if (active) setUser(currentUser)
      })
      .catch(() => {
        // No authenticated session: display the login form.
      })
      .finally(() => {
        if (active) setCheckingSession(false)
      })

    return () => {
      active = false
    }
  }, [])

  // Subscribe only to the authenticated driver's private channel.
  useEffect(() => {
    if (!user || user.role !== 'DRIVER') {
      return
    }

    const channelName = `drivers.${user.id}`

    echo.private(channelName).listen(
      '.NEW_MISSION',
      (event: { mission_id: number; title: string; reference: string }) => {
        setMissionNotification(`Nouvelle mission : ${event.reference}`)
        setMissionRefreshKey((currentKey) => currentKey + 1)
      },
    )

    return () => {
      echo.leave(channelName)
    }
  }, [user])

  async function handleLogin(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    setLoading(true)

    try {
      const authenticatedUser = await login({ email, password })
      setUser(authenticatedUser)
      setPassword('')
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 429) {
        setError('Trop de tentatives. Réessayez dans quelques instants.')
      } else {
        setError('Connexion impossible. Vérifiez vos identifiants.')
      }
    } finally {
      setLoading(false)
    }
  }

  async function handleLogout() {
    setError('')
    setLoading(true)

    try {
      await logout()
      setUser(null)
      setPassword('')
      setEmail('')
    } catch {
      setError('La déconnexion a échoué. Veuillez réessayer.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box
      sx={{
        minHeight: '100vh',
        bgcolor: 'background.default',
      }}
    >
      {checkingSession ? (
        <Box
          sx={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          <Stack spacing={2} sx={{ alignItems: 'center' }}>
            <CircularProgress aria-label="Vérification de la session" />
            <Typography color="text.secondary">
              Chargement de GML-SHIELD...
            </Typography>
          </Stack>
        </Box>
      ) : !user ? (
        /*
         * Public login view.
         * Authentication continues to use the existing Laravel API.
         */
        <Box
          sx={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            px: { xs: 2, sm: 3 },
            py: { xs: 3, md: 6 },
            background:
              'linear-gradient(135deg, #EDF4F8 0%, #F4F7FB 52%, #E6F1F0 100%)',
          }}
        >
          <Container maxWidth="lg">
            <Paper
              elevation={0}
              sx={{
                overflow: 'hidden',
                borderRadius: { xs: 3, md: 4 },
                border: '1px solid',
                borderColor: 'divider',
                boxShadow: '0 24px 80px rgba(16, 38, 63, 0.10)',
              }}
            >
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: {
                    xs: '1fr',
                    md: '1fr 1fr',
                  },
                  minHeight: { md: 600 },
                }}
              >
                {/* Brand panel */}
                <Box
                  sx={{
                    position: 'relative',
                    overflow: 'hidden',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'space-between',
                    gap: 5,
                    p: { xs: 3, sm: 5, md: 6 },
                    color: '#FFFFFF',
                    background:
                      'linear-gradient(145deg, #10263F 0%, #1E3A5F 65%, #174B58 100%)',
                  }}
                >
                  <Box
                    sx={{
                      position: 'absolute',
                      right: -90,
                      bottom: -130,
                      width: 340,
                      height: 340,
                      borderRadius: '50%',
                      border: '55px solid rgba(0, 191, 165, 0.10)',
                      pointerEvents: 'none',
                    }}
                  />

                  <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                    <Box
                      sx={{
                        width: 56,
                        height: 56,
                        borderRadius: 2.5,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        bgcolor: 'rgba(0, 191, 165, 0.16)',
                        border: '1px solid rgba(105, 230, 207, 0.3)',
                      }}
                    >
                      <ShieldOutlinedIcon
                        sx={{ fontSize: 34, color: '#63E0CB' }}
                      />
                    </Box>

                    <Box>
                      <Typography
                        variant="h5"
                        sx={{
                          fontWeight: 800,
                          letterSpacing: '0.025em',
                          color: '#FFFFFF',
                        }}
                      >
                        GML-SHIELD
                      </Typography>
                      <Typography
                        variant="caption"
                        sx={{
                          color: '#AFC6D8',
                          letterSpacing: '0.14em',
                          fontWeight: 600,
                        }}
                      >
                        GREENMOVE LOGISTICS
                      </Typography>
                    </Box>
                  </Stack>

                  <Box sx={{ position: 'relative', zIndex: 1 }}>
                    <Box
                      sx={{
                        width: 52,
                        height: 4,
                        borderRadius: 4,
                        bgcolor: '#42D7BC',
                        mb: 3,
                      }}
                    />

                    <Typography
                      component="h1"
                      sx={{
                        fontSize: {
                          xs: '2rem',
                          sm: '2.5rem',
                          md: '2.7rem',
                        },
                        fontWeight: 800,
                        lineHeight: 1.18,
                        letterSpacing: '-0.025em',
                        color: '#FFFFFF',
                        mb: 3,
                      }}
                    >
                      Vos missions.
                      <br />
                      En toute sécurité.
                    </Typography>

                    <Typography
                      sx={{
                        maxWidth: 390,
                        color: '#CAD9E6',
                        fontSize: '1rem',
                        lineHeight: 1.85,
                      }}
                    >
                      Une plateforme sécurisée pour organiser les transports,
                      protéger les informations sensibles et accompagner
                      chaque mission jusqu'à sa livraison.
                    </Typography>
                  </Box>

                  <Stack spacing={2} sx={{ position: 'relative', zIndex: 1 }}>
                    <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                      <VerifiedUserOutlinedIcon sx={{ color: '#63E0CB' }} />
                      <Typography variant="body2" sx={{ color: '#DFEBF3' }}>
                        Accès authentifié et données protégées
                      </Typography>
                    </Stack>

                    <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                      <LocalShippingOutlinedIcon sx={{ color: '#63E0CB' }} />
                      <Typography variant="body2" sx={{ color: '#DFEBF3' }}>
                        Missions et suivi des livraisons
                      </Typography>
                    </Stack>
                  </Stack>
                </Box>

                {/* Login panel */}
                <Box
                  sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center',
                    px: { xs: 3, sm: 6, md: 6 },
                    py: { xs: 5, sm: 7 },
                    bgcolor: '#FFFFFF',
                  }}
                >
                  <Stack spacing={3.5}>
                    <Box>
                      <Typography
                        variant="overline"
                        sx={{
                          color: 'secondary.main',
                          fontWeight: 700,
                          letterSpacing: '0.13em',
                        }}
                      >
                        ESPACE SÉCURISÉ
                      </Typography>

                      <Typography
                        component="h2"
                        variant="h4"
                        sx={{
                          mt: 1,
                          mb: 1.5,
                          fontWeight: 800,
                          color: 'text.primary',
                        }}
                      >
                        Bon retour !
                      </Typography>

                      <Typography color="text.secondary" sx={{ lineHeight: 1.7 }}>
                        Connectez-vous pour accéder à votre espace
                        GML-SHIELD.
                      </Typography>
                    </Box>

                    <Box component="form" onSubmit={handleLogin}>
                      <Stack spacing={3}>
                        {error && (
                          <Alert severity="error" role="alert">
                            {error}
                          </Alert>
                        )}

                        <TextField
                          label="Adresse e-mail professionnelle"
                          type="email"
                          autoComplete="username"
                          value={email}
                          onChange={(event) => setEmail(event.target.value)}
                          required
                          disabled={loading}
                          fullWidth
                          slotProps={{
                            htmlInput: {
                              maxLength: 255,
                            },
                          }}
                        />

                        <TextField
                          label="Mot de passe"
                          type="password"
                          autoComplete="current-password"
                          value={password}
                          onChange={(event) => setPassword(event.target.value)}
                          required
                          disabled={loading}
                          fullWidth
                        />

                        <Button
                          type="submit"
                          variant="contained"
                          size="large"
                          disabled={loading}
                          endIcon={
                            loading ? (
                              <CircularProgress
                                size={18}
                                color="inherit"
                              />
                            ) : (
                              <ArrowForwardRoundedIcon />
                            )
                          }
                          sx={{
                            minHeight: 54,
                            fontSize: '1rem',
                            fontWeight: 700,
                            mt: 1,
                          }}
                          fullWidth
                        >
                          {loading ? 'Connexion en cours...' : 'Se connecter'}
                        </Button>
                      </Stack>
                    </Box>

                    <Divider />

                    <Stack
                      direction="row"
                      spacing={1}
                      sx={{ alignItems: 'center', justifyContent: 'center' }}
                    >
                      <LockOutlinedIcon
                        sx={{
                          fontSize: 18,
                          color: 'text.secondary',
                        }}
                      />
                      <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ textAlign: 'center' }}
                      >
                        Accès réservé aux utilisateurs autorisés
                      </Typography>
                    </Stack>
                  </Stack>
                </Box>
              </Box>
            </Paper>

            <Typography
              variant="caption"
              component="p"
              sx={{
                mt: 3,
                textAlign: 'center',
                color: 'text.secondary',
              }}
            >
              © {new Date().getFullYear()} GreenMove Logistics — GML-SHIELD
            </Typography>
          </Container>
        </Box>
      ) : (
        /*
         * Authenticated workspace.
         * Existing role-based features remain unchanged.
         */
        <Box
          sx={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'flex-start',
            justifyContent: 'center',
            px: 2,
            py: { xs: 3, sm: 6 },
          }}
        >
          <Container maxWidth={user.role === 'DRIVER' ? 'md' : 'lg'}>
            <Paper
              elevation={0}
              sx={{
                p: { xs: 3, sm: 5 },
                border: 1,
                borderColor: 'divider',
                borderRadius: 3,
                boxShadow: '0 18px 50px rgba(15, 23, 42, 0.06)',
              }}
            >
              <Stack spacing={3}>
                <Stack spacing={1} sx={{ alignItems: 'center' }}>
                  <Box
                    sx={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      width: 64,
                      height: 64,
                      borderRadius: 2,
                      bgcolor: 'primary.main',
                      color: 'primary.contrastText',
                    }}
                  >
                    <ShieldOutlinedIcon fontSize="large" />
                  </Box>

                  <Typography
                    variant="h4"
                    component="h1"
                    sx={{ fontWeight: 700 }}
                  >
                    GML-SHIELD
                  </Typography>

                  <Typography
                    sx={{
                      color: 'text.secondary',
                      textAlign: 'center',
                    }}
                  >
                    Gestion sécurisée des missions de transport
                  </Typography>
                </Stack>

                {user.role === 'DRIVER' ? (
                  <Paper
                    elevation={0}
                    sx={{
                      position: 'relative',
                      overflow: 'hidden',
                      p: { xs: 3, sm: 4 },
                      borderRadius: 3,
                      color: '#FFFFFF',
                      background:
                        'linear-gradient(135deg, #10263F 0%, #1E3A5F 70%, #17505C 100%)',
                    }}
                  >
                    <Stack spacing={3}>
                      <Box
                        sx={{
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'space-between',
                          gap: 2,
                          flexWrap: 'wrap',
                        }}
                      >
                        <Box
                          sx={{
                            width: 52,
                            height: 52,
                            borderRadius: 2,
                            bgcolor: 'rgba(99, 224, 203, 0.14)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                          }}
                        >
                          <RouteOutlinedIcon
                            sx={{ fontSize: 30, color: '#63E0CB' }}
                          />
                        </Box>

                        <Box
                          sx={{
                            px: 2,
                            py: 0.8,
                            borderRadius: 2,
                            bgcolor: 'rgba(0, 137, 123, 0.28)',
                          }}
                        >
                          <Typography
                            variant="caption"
                            sx={{ color: '#B3F2DF', fontWeight: 700 }}
                          >
                            Espace chauffeur
                          </Typography>
                        </Box>
                      </Box>

                      <Box>
                        <Typography
                          variant="overline"
                          sx={{
                            color: '#63E0CB',
                            fontWeight: 700,
                            letterSpacing: '0.12em',
                          }}
                        >
                          GML-SHIELD / TRANSPORT
                        </Typography>

                        <Typography
                          variant="h4"
                          component="h2"
                          sx={{
                            mt: 1,
                            mb: 1.5,
                            fontWeight: 800,
                            color: '#FFFFFF',
                          }}
                        >
                          Mes livraisons
                        </Typography>

                        <Typography sx={{ color: '#CAD9E6', lineHeight: 1.7 }}>
                          Bonjour {user.name}. Retrouvez vos missions
                          et suivez vos livraisons en toute sécurité.
                        </Typography>
                      </Box>
                    </Stack>
                  </Paper>
                ) : (
                  <Box>
                    <Typography variant="h6">
                      Bienvenue, {user.name}
                    </Typography>
                    <Typography color="text.secondary">
                      {user.role === 'ADMIN'
                        ? 'Administrateur'
                        : 'Répartiteur'}
                    </Typography>
                  </Box>
                )}

                {user.role === 'ADMIN' ? (
                  <AdminDashboard user={user} />
                ) : user.role === 'DISPATCHER' ? (
                  <DispatcherDashboard user={user} />
                ) : (
                  <MissionList
                    user={user}
                    refreshKey={missionRefreshKey}
                  />
                )}

                {error && <Alert severity="error">{error}</Alert>}

                <Button
                  variant="outlined"
                  color="primary"
                  onClick={handleLogout}
                  disabled={loading}
                  startIcon={<LogoutOutlinedIcon />}
                  fullWidth
                >
                  {loading ? 'Déconnexion...' : 'Se déconnecter'}
                </Button>

                <Typography
                  variant="caption"
                  sx={{
                    color: 'text.secondary',
                    textAlign: 'center',
                  }}
                >
                  GreenMove Logistics — Accès réservé aux utilisateurs autorisés
                </Typography>
              </Stack>
            </Paper>
          </Container>
        </Box>
      )}

      <Snackbar
        open={Boolean(missionNotification)}
        autoHideDuration={6000}
        onClose={() => setMissionNotification('')}
        message={missionNotification}
      />
    </Box>
  )
}

export default App
