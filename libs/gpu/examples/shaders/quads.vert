#version 450

struct Instance
{
    vec2 position;
    vec2 size;
    uint tile;
    uint shift;
    uint padding0;
    uint padding1;
};

layout(std430, set = 0, binding = 0) readonly buffer Instances
{
    Instance instances[];
};

layout(location = 0) out vec2 outPixel;
layout(location = 1) flat out uint outShift;

const vec2 CORNERS[6] = vec2[](
    vec2(0.0, 0.0), vec2(1.0, 0.0), vec2(1.0, 1.0),
    vec2(0.0, 0.0), vec2(1.0, 1.0), vec2(0.0, 1.0)
);

void main()
{
    Instance instance = instances[gl_InstanceIndex];
    vec2 corner = CORNERS[gl_VertexIndex];

    gl_Position = vec4(instance.position + corner * instance.size, 0.0, 1.0);

    // The atlas is 4x4 tiles of 16x16 texels.
    vec2 origin = vec2(float(instance.tile % 4u), float(instance.tile / 4u)) * 16.0;
    outPixel = origin + corner * 15.99;
    outShift = instance.shift;
}
