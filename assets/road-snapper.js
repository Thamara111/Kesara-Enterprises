/**
 * Kesara Enterprises - Road Snapping & Curvature Tracing Utility
 * 
 * Interacts with Google Roads API (Snap to Roads with interpolate=true)
 * to smoothly trace road geometries and provide road-following delivery vehicle animations.
 */

(function(window) {
    'use strict';

    const routeCache = new Map();

    /**
     * Converts an array of points [[lat, lng], ...] to Google Roads path format string: "lat1,lng1|lat2,lng2"
     */
    function formatPathString(points) {
        if (!Array.isArray(points)) return '';
        return points
            .filter(pt => Array.isArray(pt) && pt.length >= 2 && !isNaN(pt[0]) && !isNaN(pt[1]))
            .map(pt => `${Number(pt[0]).toFixed(6)},${Number(pt[1]).toFixed(6)}`)
            .join('|');
    }

    /**
     * Fallback client-side spline interpolator if backend / network is unreachable
     */
    function clientCurvedFallback(points) {
        if (!points || points.length < 2) return points || [];
        const result = [];
        const steps = 10;
        
        for (let i = 0; i < points.length - 1; i++) {
            const p0 = i > 0 ? points[i - 1] : points[i];
            const p1 = points[i];
            const p2 = points[i + 1];
            const p3 = i + 2 < points.length ? points[i + 2] : p2;

            const dx = p2[0] - p1[0];
            const dy = p2[1] - p1[1];
            const len = Math.sqrt(dx * dx + dy * dy);
            const normX = len > 0 ? -dy / len : 0;
            const normY = len > 0 ? dx / len : 0;

            for (let s = (i === 0 ? 0 : 1); s <= steps; s++) {
                const t = s / steps;
                const t2 = t * t;
                const t3 = t2 * t;

                let lat = 0.5 * ((2 * p1[0]) +
                    (-p0[0] + p2[0]) * t +
                    (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 +
                    (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3);

                let lng = 0.5 * ((2 * p1[1]) +
                    (-p0[1] + p2[1]) * t +
                    (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 +
                    (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3);

                const curveOffset = Math.sin(t * Math.PI) * Math.sin((i + t) * 2.5) * 0.0003;
                lat += normX * curveOffset;
                lng += normY * curveOffset;

                result.push([Number(lat.toFixed(6)), Number(lng.toFixed(6))]);
            }
        }
        return result;
    }

    /**
     * Fetches road-snapped coordinates from the backend Google Roads endpoint
     * @param {Array<[number, number]>} points Array of [lat, lng] coordinates
     * @param {boolean} interpolate Whether to force interpolate=true (default true)
     * @returns {Promise<Array<[number, number]>>} Promise resolving to snapped road coordinate array
     */
    async function fetchSnappedRoadPath(points, interpolate = true) {
        if (!points || points.length < 2) {
            return points || [];
        }

        const pathStr = formatPathString(points);
        if (!pathStr || pathStr.indexOf('|') === -1) {
            return points;
        }

        const cacheKey = `${pathStr}_${interpolate ? '1' : '0'}`;
        if (routeCache.has(cacheKey)) {
            return routeCache.get(cacheKey);
        }

        try {
            const endpoint = `/api/snap_to_roads.php?path=${encodeURIComponent(pathStr)}&interpolate=${interpolate ? 'true' : 'false'}`;
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}`);
            }

            const data = await response.json();
            let coordinates = [];

            if (data && data.coordinates && Array.isArray(data.coordinates) && data.coordinates.length > 0) {
                coordinates = data.coordinates;
            } else if (data && data.snappedPoints && Array.isArray(data.snappedPoints)) {
                coordinates = data.snappedPoints.map(sp => [sp.location.latitude, sp.location.longitude]);
            } else {
                coordinates = clientCurvedFallback(points);
            }

            routeCache.set(cacheKey, coordinates);
            return coordinates;
        } catch (err) {
            console.warn('[RoadSnapper] Google Roads API fetch fallback:', err);
            const fallbackCoords = clientCurvedFallback(points);
            routeCache.set(cacheKey, fallbackCoords);
            return fallbackCoords;
        }
    }

    /**
     * Calculates the cumulative distances along a road-snapped path and returns total length in meters
     */
    function computePathDistances(pathCoords) {
        const cumulative = [0];
        let total = 0;

        for (let i = 0; i < pathCoords.length - 1; i++) {
            const p1 = pathCoords[i];
            const p2 = pathCoords[i + 1];
            const dx = (p2[0] - p1[0]) * 111000;
            const dy = (p2[1] - p1[1]) * 111000 * Math.cos((p1[0] * Math.PI) / 180);
            const d = Math.sqrt(dx * dx + dy * dy);
            total += d;
            cumulative.push(total);
        }

        return { cumulative, total };
    }

    /**
     * Interpolates the exact coordinate along a road path for a given progress (0.0 to 1.0)
     */
    function getCoordinateAtProgress(pathCoords, progress) {
        if (!pathCoords || pathCoords.length === 0) return null;
        if (pathCoords.length === 1 || progress <= 0) return pathCoords[0];
        if (progress >= 1.0) return pathCoords[pathCoords.length - 1];

        const { cumulative, total } = computePathDistances(pathCoords);
        if (total === 0) return pathCoords[0];

        const targetDist = progress * total;
        for (let i = 0; i < cumulative.length - 1; i++) {
            if (targetDist >= cumulative[i] && targetDist <= cumulative[i + 1]) {
                const segLen = cumulative[i + 1] - cumulative[i];
                const t = segLen > 0 ? (targetDist - cumulative[i]) / segLen : 0;
                const p1 = pathCoords[i];
                const p2 = pathCoords[i + 1];
                return [
                    p1[0] + (p2[0] - p1[0]) * t,
                    p1[1] + (p2[1] - p1[1]) * t
                ];
            }
        }

        return pathCoords[pathCoords.length - 1];
    }

    // Expose methods to global window
    window.RoadSnapper = {
        fetchSnappedRoadPath,
        computePathDistances,
        getCoordinateAtProgress,
        formatPathString
    };

    // Backward compatibility helper
    window.fetchSnappedRoadPath = fetchSnappedRoadPath;

})(window);
