import { Haptics, ImpactStyle } from '@capacitor/haptics';

/**
 * Haptic Feedback Utility Class
 * Provides consistent haptic feedback for user interactions
 */
class HapticFeedback {
  /**
   * Provide light impact feedback
   */
  static async lightImpact(): Promise<void> {
    try {
      await Haptics.impact({
        style: ImpactStyle.Light,
      });
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Provide medium impact feedback
   */
  static async mediumImpact(): Promise<void> {
    try {
      await Haptics.impact({
        style: ImpactStyle.Medium,
      });
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Provide heavy impact feedback
   */
  static async heavyImpact(): Promise<void> {
    try {
      await Haptics.impact({
        style: ImpactStyle.Heavy,
      });
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Provide selection changed feedback
   */
  static async selectionChanged(): Promise<void> {
    try {
      await Haptics.selectionStart();
      // Simulate selection change
      setTimeout(async () => {
        await Haptics.selectionEnd();
      }, 100);
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Provide notification feedback for success
   */
  static async successNotification(): Promise<void> {
    try {
      // Three light impacts to indicate success
      await Haptics.impact({ style: ImpactStyle.Light });
      setTimeout(async () => {
        await Haptics.impact({ style: ImpactStyle.Light });
        setTimeout(async () => {
          await Haptics.impact({ style: ImpactStyle.Light });
        }, 50);
      }, 50);
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Provide notification feedback for warning
   */
  static async warningNotification(): Promise<void> {
    try {
      // Medium impact followed by light impact for warning
      await Haptics.impact({ style: ImpactStyle.Medium });
      setTimeout(async () => {
        await Haptics.impact({ style: ImpactStyle.Light });
      }, 100);
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Provide notification feedback for error
   */
  static async errorNotification(): Promise<void> {
    try {
      // Heavy impact followed by two light impacts for error
      await Haptics.impact({ style: ImpactStyle.Heavy });
      setTimeout(async () => {
        await Haptics.impact({ style: ImpactStyle.Light });
        setTimeout(async () => {
          await Haptics.impact({ style: ImpactStyle.Light });
        }, 100);
      }, 100);
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Generic tap feedback
   */
  static async tap(): Promise<void> {
    try {
      await Haptics.impact({ style: ImpactStyle.Light });
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Feedback for button press
   */
  static async buttonPress(): Promise<void> {
    try {
      await Haptics.impact({ style: ImpactStyle.Medium });
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }

  /**
   * Feedback for completing an action (like saving data)
   */
  static async actionCompleted(): Promise<void> {
    try {
      // Medium impact to confirm action completion
      await Haptics.impact({ style: ImpactStyle.Medium });
    } catch (error) {
      console.warn('Haptics not available:', error);
    }
  }
}

export default HapticFeedback;