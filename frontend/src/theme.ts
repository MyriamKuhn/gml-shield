import { createTheme } from "@mui/material/styles";

/**
 * Global Material UI theme for GML-SHIELD.
 * Centralizes the application's visual identity.
 */
export const theme = createTheme({
  palette: {
    mode: "light",
    primary: {
      main: "#1E3A5F",
      light: "#456789",
      dark: "#10263F",
      contrastText: "#FFFFFF",
    },
    secondary: {
      main: "#00897B",
    },
    background: {
      default: "#F4F7FB",
      paper: "#FFFFFF",
    },
    text: {
      primary: "#172B4D",
      secondary: "#64748B",
    },
    error: {
      main: "#C62828",
    },
    success: {
      main: "#2E7D32",
    },
  },

  typography: {
    fontFamily: ["Inter", "Roboto", "Arial", "sans-serif"].join(","),
    h4: {
      fontWeight: 700,
    },
    h6: {
      fontWeight: 600,
    },
    button: {
      fontWeight: 600,
      textTransform: "none",
    },
  },

  shape: {
    borderRadius: 12,
  },

  components: {
    MuiButton: {
      defaultProps: {
        disableElevation: true,
      },
      styleOverrides: {
        root: {
          borderRadius: 10,
          paddingTop: 10,
          paddingBottom: 10,
        },
      },
    },
    MuiPaper: {
      styleOverrides: {
        root: {
          backgroundImage: "none",
        },
      },
    },
  },
});
