// The corners of the two triangles of a quad that a vertex shader draws without vertex buffers: x runs along the quad,
// y runs up.
const vec2 CORNERS[6] = vec2[](
    vec2(0.0, 0.0), vec2(1.0, 0.0), vec2(1.0, 1.0),
    vec2(0.0, 0.0), vec2(1.0, 1.0), vec2(0.0, 1.0)
);
