import { getCenter, getDistance } from 'geolib';
import type { GeolibInputCoordinates } from 'geolib/es/types';

/**
 * Calculate distance between two geographical points using Geolib (more accurate)
 * @param lat1 Latitude of first point
 * @param lon1 Longitude of first point
 * @param lat2 Latitude of second point
 * @param lon2 Longitude of second point
 * @returns Distance in kilometers
 */
export function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const point1: GeolibInputCoordinates = { latitude: lat1, longitude: lon1 };
    const point2: GeolibInputCoordinates = { latitude: lat2, longitude: lon2 };

    // Get distance in meters, convert to kilometers
    const distanceInMeters = getDistance(point1, point2);
    const distanceInKm = distanceInMeters / 1000;

    return Math.round(distanceInKm * 100) / 100; // Round to 2 decimal places
}

/**
 * Calculate total distance for a route
 * @param stops Array of stops with coordinates and order
 * @returns Total distance in kilometers
 */
export function calculateRouteDistance(stops: Array<{ order?: number; latitude: number | null; longitude: number | null }>): number {
    // If order is provided, sort by order, otherwise use array order
    const sortedStops = stops.filter((stop) => stop.latitude !== null && stop.longitude !== null);
    if (stops.some((stop) => stop.order !== undefined)) {
        sortedStops.sort((a, b) => (a.order || 0) - (b.order || 0));
    }

    if (sortedStops.length < 2) return 0;

    let totalDistance = 0;
    for (let i = 1; i < sortedStops.length; i++) {
        totalDistance += calculateDistance(
            sortedStops[i - 1].latitude!,
            sortedStops[i - 1].longitude!,
            sortedStops[i].latitude!,
            sortedStops[i].longitude!,
        );
    }

    return Math.round(totalDistance * 100) / 100;
}

/**
 * Calculate distances from previous stop for each stop in route
 * @param stops Array of stops with latitude and longitude
 * @returns Array of distances from previous stop (first stop has 0)
 */
export function calculateStopDistances(stops: Array<{ latitude: number | null; longitude: number | null }>): number[] {
    if (stops.length === 0) return [];

    const distances = [0]; // First stop has no previous stop

    for (let i = 1; i < stops.length; i++) {
        const prev = stops[i - 1];
        const current = stops[i];

        if (prev.latitude && prev.longitude && current.latitude && current.longitude) {
            distances.push(calculateDistance(prev.latitude, prev.longitude, current.latitude, current.longitude));
        } else {
            distances.push(0);
        }
    }

    return distances;
}

/**
 * Calculate cumulative distances for route stops
 * @param stops Array of stops with coordinates and order
 * @returns Array of stops with cumulative distances
 */
export function calculateCumulativeDistances(
    stops: Array<{ id: number; order?: number; latitude: number | null; longitude: number | null }>,
): Array<{ id: number; order?: number; latitude: number | null; longitude: number | null; cumulativeDistance: number }> {
    // If order is provided, sort by order, otherwise use array order
    const sortedStops = stops.filter((stop) => stop.latitude !== null && stop.longitude !== null);
    if (stops.some((stop) => stop.order !== undefined)) {
        sortedStops.sort((a, b) => (a.order || 0) - (b.order || 0));
    }

    if (sortedStops.length === 0) return [];

    const result = [];
    let cumulativeDistance = 0;

    for (let i = 0; i < sortedStops.length; i++) {
        if (i > 0) {
            const distance = calculateDistance(
                sortedStops[i - 1].latitude!,
                sortedStops[i - 1].longitude!,
                sortedStops[i].latitude!,
                sortedStops[i].longitude!,
            );
            cumulativeDistance += distance;
        }

        result.push({
            ...sortedStops[i],
            cumulativeDistance: Math.round(cumulativeDistance * 100) / 100,
        });
    }

    return result;
}

/**
 * Find the center point of multiple coordinates
 * @param coordinates Array of coordinates
 * @returns Center point coordinates
 */
export function calculateCenterPoint(
    coordinates: Array<{ latitude: number | null; longitude: number | null }>,
): { latitude: number; longitude: number } | null {
    const validCoords = coordinates
        .filter((coord) => coord.latitude !== null && coord.longitude !== null)
        .map((coord) => ({ latitude: coord.latitude!, longitude: coord.longitude! }));

    if (validCoords.length === 0) return null;

    return getCenter(validCoords) as { latitude: number; longitude: number };
}

/**
 * Sort stops by distance from a reference point
 * @param referencePoint The point to calculate distances from
 * @param stops Array of stops to sort
 * @returns Sorted array of stops with distances
 */
export function sortStopsByDistance(
    referencePoint: { latitude: number; longitude: number },
    stops: Array<{ id: number; latitude: number | null; longitude: number | null; name: string }>,
): Array<{ id: number; latitude: number | null; longitude: number | null; name: string; distance: number }> {
    const stopsWithValidCoords = stops
        .filter((stop) => stop.latitude !== null && stop.longitude !== null)
        .map((stop) => ({
            id: stop.id,
            name: stop.name,
            latitude: stop.latitude!,
            longitude: stop.longitude!,
        }));

    // Calculate distances and sort
    const stopsWithDistances = stopsWithValidCoords.map((stop) => ({
        ...stop,
        distance: getDistance(referencePoint, { latitude: stop.latitude, longitude: stop.longitude }) / 1000, // Convert to kilometers
    }));

    // Sort by distance
    stopsWithDistances.sort((a, b) => a.distance - b.distance);

    return stopsWithDistances;
}
